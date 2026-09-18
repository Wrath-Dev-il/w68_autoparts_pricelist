<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$account, $loginId, $customerId] = $this->identity($request);
        unset($account);

        // Keep the bell usable even when an older local database has not yet
        // received the notification SQL patch. This is intentionally
        // idempotent and only creates missing W68 notification storage.
        try {
            $this->ensureStorage();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Notification storage could not be prepared. Run the W68 v90 database installer once.',
            ], 500);
        }

        // A downstream legacy Sales Order schema must never make the whole
        // notification bell fail. Reconciliation is best-effort; already
        // stored notifications can still be displayed and marked as read.
        try {
            $this->reconcileOrderNotifications($loginId, $customerId);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $rows = DB::connection('sales_order')
                ->table('w68_portal_order_notifications')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->orderByDesc('event_at')
                ->orderByDesc('id')
                ->limit(80)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Unable to read W68 notifications from core4_sales_order.',
            ], 500);
        }

        try {
            $states = $this->orderStates(
                $rows->pluck('w68_portal_order_id')->map(fn ($id) => (int) $id)->unique()->values()
            );
        } catch (Throwable $exception) {
            report($exception);
            $states = [];
        }

        $basePath = preg_replace(
            '#/index\.php$#i',
            '',
            rtrim(str_replace('\\', '/', (string) $request->getBaseUrl()), '/')
        );

        $notifications = $rows->map(function ($row) use ($states, $basePath): array {
            $orderId = (int) ($row->w68_portal_order_id ?? 0);
            $state = $states[$orderId] ?? [
                'movement' => ['PROCESSED'],
                'waybill_no' => '',
                'portal_status' => '',
                'sales_note_status' => '',
            ];

            return [
                'id' => 'order:' . (int) $row->id,
                'source' => 'order',
                'order_id' => $orderId,
                'order_code' => (string) ($row->order_code ?? ''),
                'sales_number' => (string) ($row->sales_number ?? ''),
                'event_type' => (string) ($row->event_type ?? ''),
                'event_value' => (string) ($row->event_value ?? ''),
                'title' => (string) ($row->title ?? ''),
                'message' => (string) ($row->message ?? ''),
                'event_at' => $this->formatDateTime($row->event_at ?? $row->created_at ?? null),
                'is_read' => (bool) ($row->is_read ?? false),
                'read_at' => $this->formatDateTime($row->read_at ?? null),
                'movement' => $state['movement'],
                'waybill_no' => $state['waybill_no'],
                'portal_status' => $state['portal_status'],
                'sales_note_status' => $state['sales_note_status'],
                'order_url' => $basePath . '/process-order/' . $orderId,
            ];
        })->values();

        // W68_PRICELIST_SOA_NOTIFICATION_20260918
        $soaNotifications = collect();
        if (Schema::connection('system')->hasTable('customer_portal_notifications')) {
            $soaNotifications = DB::connection('system')
                ->table('customer_portal_notifications')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->orderByDesc('event_at')
                ->orderByDesc('id')
                ->limit(80)
                ->get()
                ->map(function ($row): array {
                    return [
                        'id' => 'soa:' . (int) $row->id,
                        'source' => 'soa',
                        'order_id' => null,
                        'order_code' => '',
                        'sales_number' => '',
                        'event_type' => (string) ($row->event_type ?? 'SOA_AUTO_SENT'),
                        'event_value' => '',
                        'title' => (string) ($row->title ?? 'Statement of Account Sent'),
                        'message' => (string) ($row->message ?? ''),
                        'event_at' => $this->formatDateTime($row->event_at ?? $row->created_at ?? null),
                        'is_read' => (bool) ($row->is_read ?? false),
                        'read_at' => $this->formatDateTime($row->read_at ?? null),
                        'movement' => ['PAYMENT REMINDER', 'SOA SENT TO EMAIL'],
                        'waybill_no' => '',
                        'portal_status' => '',
                        'sales_note_status' => '',
                        'order_url' => '',
                    ];
                });
        }

        $notifications = $notifications
            ->concat($soaNotifications)
            ->sortByDesc(function (array $item): int {
                return strtotime((string) ($item['event_at'] ?? '')) ?: 0;
            })
            ->take(80)
            ->values();

        $unreadCount = $this->unreadCount($loginId, $customerId);

        return response()->json([
            'ok' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureStorage();

        [$source, $notificationId] = $this->parseNotificationId($notification);

        if ($source === 'soa') {
            $row = DB::connection('system')
                ->table('customer_portal_notifications')
                ->where('id', $notificationId)
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->first(['id']);

            abort_unless($row, 404, 'Notification not found.');

            DB::connection('system')
                ->table('customer_portal_notifications')
                ->where('id', $notificationId)
                ->update([
                    'is_read' => 1,
                    'read_at' => now(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'ok' => true,
                'unread_count' => $this->unreadCount($loginId, $customerId),
            ]);
        }

        $row = DB::connection('sales_order')
            ->table('w68_portal_order_notifications')
            ->where('id', $notificationId)
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->first(['id', 'w68_portal_order_id']);

        abort_unless($row, 404, 'Notification not found.');

        DB::connection('sales_order')
            ->table('w68_portal_order_notifications')
            ->where('id', $notificationId)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        $this->syncOrderReadFlag((int) $row->w68_portal_order_id);

        return response()->json([
            'ok' => true,
            'unread_count' => $this->unreadCount($loginId, $customerId),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        [, $loginId, $customerId] = $this->identity($request);
        $this->ensureStorage();

        $now = now();

        DB::connection('sales_order')
            ->table('w68_portal_order_notifications')
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        if (Schema::connection('system')->hasTable('customer_portal_notifications')) {
            DB::connection('system')
                ->table('customer_portal_notifications')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->where('is_read', 0)
                ->update([
                    'is_read' => 1,
                    'read_at' => $now,
                    'updated_at' => $now,
                ]);
        }
        if (
            Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_is_read')
            && Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_read_at')
        ) {
            DB::connection('sales_order')
                ->table('w68_portal_orders')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->update([
                    'notification_is_read' => 1,
                    'notification_read_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    private function reconcileOrderNotifications(int $loginId, int $customerId): void
    {
        $orders = DB::connection('sales_order')
            ->table('w68_portal_orders as po')
            ->leftJoin('sales_notes as sn', 'sn.id', '=', 'po.sales_note_id')
            ->where('po.login_id', $loginId)
            ->where('po.customer_id', $customerId)
            ->orderByDesc('po.id')
            ->get([
                'po.id',
                'po.login_id',
                'po.customer_id',
                'po.order_code',
                'po.sales_note_id',
                'po.sales_number',
                'po.portal_status',
                'po.created_at as portal_created_at',
                'po.updated_at as portal_updated_at',
                'sn.status as sales_note_status',
                'sn.updated_at as sales_note_updated_at',
            ]);

        if ($orders->isEmpty()) {
            return;
        }

        $noteIds = $orders->pluck('sales_note_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $salesOrdersByNote = $this->salesOrdersByNote($noteIds);

        foreach ($orders as $order) {
            $portalOrderId = (int) $order->id;
            $orderCode = trim((string) ($order->order_code ?? ''));
            if ($orderCode === '') {
                $orderCode = 'W68-' . str_pad((string) $portalOrderId, 8, '0', STR_PAD_LEFT);
            }
            $order->order_code = $orderCode;

            $salesNumber = trim((string) ($order->sales_number ?? ''));
            $salesNoteStatus = mb_strtoupper(trim((string) ($order->sales_note_status ?? '')));
            $portalStatus = mb_strtoupper(trim((string) ($order->portal_status ?? '')));
            $salesOrders = $salesOrdersByNote->get((int) $order->sales_note_id, collect());

            $isClosed = $salesNoteStatus === 'CLOSED' || $portalStatus === 'CLOSED';
            if ($isClosed) {
                $this->upsertNotification(
                    $order,
                    'SALES_NOTE_CLOSED',
                    'CLOSED',
                    'Sales Note Closed',
                    ($salesNumber !== '' ? $salesNumber : 'The linked Sales Note') . ' is now Closed for ' . $orderCode . '.',
                    $order->sales_note_updated_at ?? $order->portal_updated_at ?? now()
                );
            }

            $waybill = $salesOrders->first(function ($salesOrder): bool {
                return !empty($salesOrder->waybill_id)
                    || trim((string) ($salesOrder->waybill_no ?? '')) !== '';
            });

            if ($waybill) {
                $waybillNo = trim((string) ($waybill->waybill_no ?? ''));
                if ($waybillNo === '') {
                    $waybillNo = 'Waybill #' . (string) ($waybill->waybill_id ?? '');
                }

                $this->upsertNotification(
                    $order,
                    'WAYBILL_CREATED',
                    $waybillNo,
                    'Waybill Available',
                    $waybillNo . ' is now attached to ' . $orderCode . '.',
                    $waybill->waybill_date ?? $waybill->updated_at ?? $order->portal_updated_at ?? now()
                );
            }

            if ($portalStatus === 'CANCELLED') {
                $this->upsertNotification(
                    $order,
                    'ORDER_CANCELLED',
                    'CANCELLED',
                    'Order Cancelled',
                    $orderCode . ' has been cancelled.',
                    $order->portal_updated_at ?? now()
                );
            }
        }
    }

    private function upsertNotification($order, string $eventType, string $eventValue, string $title, string $message, $eventAt): void
    {
        $connection = DB::connection('sales_order');
        $orderId = (int) $order->id;
        $now = now();

        $inserted = $connection
            ->table('w68_portal_order_notifications')
            ->insertOrIgnore([
                'w68_portal_order_id' => $orderId,
                'login_id' => (int) $order->login_id,
                'customer_id' => (int) $order->customer_id,
                'sales_note_id' => (int) ($order->sales_note_id ?? 0) ?: null,
                'order_code' => (string) ($order->order_code ?? ''),
                'sales_number' => (string) ($order->sales_number ?? ''),
                'event_type' => $eventType,
                'event_value' => $eventValue,
                'title' => $title,
                'message' => $message,
                'event_at' => $eventAt,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        // Keep descriptive/status data current without ever turning an already-read
        // notification back into unread just because its text/value was refreshed.
        $connection
            ->table('w68_portal_order_notifications')
            ->where('w68_portal_order_id', $orderId)
            ->where('event_type', $eventType)
            ->update([
                'order_code' => (string) ($order->order_code ?? ''),
                'sales_number' => (string) ($order->sales_number ?? ''),
                'event_value' => $eventValue,
                'title' => $title,
                'message' => $message,
                'event_at' => $eventAt,
                'updated_at' => $now,
            ]);

        if ((int) $inserted > 0 && Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_is_read')) {
            $payload = [
                'notification_is_read' => 0,
                'updated_at' => $now,
            ];

            if (Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_read_at')) {
                $payload['notification_read_at'] = null;
            }

            $connection->table('w68_portal_orders')
                ->where('id', $orderId)
                ->update($payload);
        }
    }

    private function orderStates(Collection $orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        $orders = DB::connection('sales_order')
            ->table('w68_portal_orders as po')
            ->leftJoin('sales_notes as sn', 'sn.id', '=', 'po.sales_note_id')
            ->whereIn('po.id', $orderIds->all())
            ->get([
                'po.id',
                'po.sales_note_id',
                'po.portal_status',
                'sn.status as sales_note_status',
            ]);

        $noteIds = $orders->pluck('sales_note_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $salesOrdersByNote = $this->salesOrdersByNote($noteIds);

        $states = [];

        foreach ($orders as $order) {
            $portalStatus = mb_strtoupper(trim((string) ($order->portal_status ?? '')));
            $salesNoteStatus = mb_strtoupper(trim((string) ($order->sales_note_status ?? '')));
            $salesOrders = $salesOrdersByNote->get((int) $order->sales_note_id, collect());
            $waybill = $salesOrders->first(function ($salesOrder): bool {
                return !empty($salesOrder->waybill_id)
                    || trim((string) ($salesOrder->waybill_no ?? '')) !== '';
            });

            $movement = ['PROCESSED'];
            if ($salesNoteStatus === 'CLOSED' || $portalStatus === 'CLOSED') {
                $movement[] = 'SALES NOTE CLOSED';
            }

            $waybillNo = '';
            if ($waybill) {
                $waybillNo = trim((string) ($waybill->waybill_no ?? ''));
                if ($waybillNo === '') {
                    $waybillNo = 'WAYBILL #' . (string) ($waybill->waybill_id ?? '');
                }
                $movement[] = 'WAYBILL ' . $waybillNo;
            }

            if ($portalStatus === 'CANCELLED') {
                $movement[] = 'CANCELLED';
            }

            $states[(int) $order->id] = [
                'movement' => $movement,
                'waybill_no' => $waybillNo,
                'portal_status' => $portalStatus,
                'sales_note_status' => $salesNoteStatus,
            ];
        }

        return $states;
    }

    /**
     * Read downstream Sales Orders without assuming every legacy/local copy has
     * all of the newer waybill columns. Missing optional columns are simply
     * ignored instead of causing the notification endpoint to return HTTP 500.
     */
    private function salesOrdersByNote(array $noteIds): Collection
    {
        $noteIds = collect($noteIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($noteIds === []) {
            return collect();
        }

        $schema = Schema::connection('sales_order');
        if (
            !$schema->hasTable('sales_orders')
            || !$schema->hasColumn('sales_orders', 'sales_note_id')
        ) {
            return collect();
        }

        $columns = ['id', 'sales_note_id'];
        foreach (['order_number', 'waybill_no', 'waybill_id', 'waybill_date', 'status', 'updated_at'] as $column) {
            if ($schema->hasColumn('sales_orders', $column)) {
                $columns[] = $column;
            }
        }

        return DB::connection('sales_order')
            ->table('sales_orders')
            ->whereIn('sales_note_id', $noteIds)
            ->orderByDesc('id')
            ->get($columns)
            ->groupBy('sales_note_id');
    }

    private function syncOrderReadFlag(int $orderId): void
    {
        if (
            !Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_is_read')
            || !Schema::connection('sales_order')->hasColumn('w68_portal_orders', 'notification_read_at')
        ) {
            return;
        }

        $unread = (int) DB::connection('sales_order')
            ->table('w68_portal_order_notifications')
            ->where('w68_portal_order_id', $orderId)
            ->where('is_read', 0)
            ->count();

        DB::connection('sales_order')
            ->table('w68_portal_orders')
            ->where('id', $orderId)
            ->update([
                'notification_is_read' => $unread === 0 ? 1 : 0,
                'notification_read_at' => $unread === 0 ? now() : null,
                'updated_at' => now(),
            ]);
    }

    private function parseNotificationId(string $notification): array
    {
        $value = trim($notification);
        if (preg_match('/^(order|soa):(\d+)$/', $value, $matches)) {
            return [$matches[1], (int) $matches[2]];
        }

        if (ctype_digit($value)) {
            return ['order', (int) $value];
        }

        abort(404, 'Notification not found.');
    }

    private function unreadCount(int $loginId, int $customerId): int
    {
        $orderCount = (int) DB::connection('sales_order')
            ->table('w68_portal_order_notifications')
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->where('is_read', 0)
            ->count();

        $soaCount = 0;
        if (Schema::connection('system')->hasTable('customer_portal_notifications')) {
            $soaCount = (int) DB::connection('system')
                ->table('customer_portal_notifications')
                ->where('login_id', $loginId)
                ->where('customer_id', $customerId)
                ->where('is_read', 0)
                ->count();
        }

        return $orderCount + $soaCount;
    }

    private function formatDateTime($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('M d, Y h:i A');
        } catch (Throwable $exception) {
            return (string) $value;
        }
    }

    private function ensureStorage(): void
    {
        $systemSchema = Schema::connection('system');
        $systemConnection = DB::connection('system');

        if (!$systemSchema->hasTable('customer_portal_notifications')) {
            $systemConnection->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `customer_portal_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `login_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_key` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `event_at` TIMESTAMP NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `customer_portal_notifications_event_unique` (`event_key`),
    KEY `customer_portal_generic_owner_unread_idx` (`login_id`, `customer_id`, `is_read`),
    KEY `customer_portal_generic_customer_event_idx` (`customer_id`, `event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        $schema = Schema::connection('sales_order');
        $connection = DB::connection('sales_order');

        if (!$schema->hasTable('w68_portal_order_notifications')) {
            $connection->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `w68_portal_order_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `w68_portal_order_id` BIGINT UNSIGNED NOT NULL,
    `login_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `sales_note_id` BIGINT UNSIGNED DEFAULT NULL,
    `order_code` VARCHAR(32) DEFAULT NULL,
    `sales_number` VARCHAR(255) DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_value` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(191) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `event_at` TIMESTAMP NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `w68_portal_notification_event_unique` (`w68_portal_order_id`, `event_type`),
    KEY `w68_portal_notification_owner_unread_idx` (`login_id`, `customer_id`, `is_read`),
    KEY `w68_portal_notification_order_idx` (`w68_portal_order_id`),
    KEY `w68_portal_notification_note_idx` (`sales_note_id`),
    KEY `w68_portal_notification_event_at_idx` (`event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        }

        // These two order-level fields are only a quick phpMyAdmin indicator.
        // The event-by-event read state remains in the notification table.
        if ($schema->hasTable('w68_portal_orders')) {
            if (!$schema->hasColumn('w68_portal_orders', 'notification_is_read')) {
                try {
                    $connection->statement(
                        "ALTER TABLE `w68_portal_orders` ADD COLUMN `notification_is_read` TINYINT(1) NOT NULL DEFAULT 1 AFTER `portal_status`"
                    );
                } catch (Throwable $exception) {
                    // Another simultaneous request may have added it first.
                    if (!$schema->hasColumn('w68_portal_orders', 'notification_is_read')) {
                        throw $exception;
                    }
                }
            }

            if (!$schema->hasColumn('w68_portal_orders', 'notification_read_at')) {
                try {
                    $connection->statement(
                        "ALTER TABLE `w68_portal_orders` ADD COLUMN `notification_read_at` TIMESTAMP NULL DEFAULT NULL AFTER `notification_is_read`"
                    );
                } catch (Throwable $exception) {
                    if (!$schema->hasColumn('w68_portal_orders', 'notification_read_at')) {
                        throw $exception;
                    }
                }
            }
        }
    }

    private function identity(Request $request): array
    {
        $account = Auth::user();

        abort_unless($account && (int) ($account->account_type ?? 0) === 5, 403, 'Customer account required.');

        $loginId = (int) (Auth::id() ?: ($account->login_ID ?? 0));
        abort_unless($loginId > 0, 422, 'Unable to resolve the W68 login ID.');

        $customerId = (int) $request->session()->get('w68_customer_id', 0);

        if (
            $customerId <= 0
            && Schema::connection('system')->hasTable('customer_portal_accounts')
            && Schema::connection('system')->hasColumn('customer_portal_accounts', 'login_id')
            && Schema::connection('system')->hasColumn('customer_portal_accounts', 'customer_id')
        ) {
            $customerId = (int) (DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('login_id', $loginId)
                ->value('customer_id') ?? 0);
        }

        if ($customerId <= 0) {
            $userId = trim((string) ($account->User_ID ?? ''));
            if ($userId !== '' && ctype_digit($userId)) {
                $candidate = (int) $userId;
                if (DB::connection('masterlist')->table('customers')->where('id', $candidate)->exists()) {
                    $customerId = $candidate;
                }
            }
        }

        abort_unless($customerId > 0, 422, 'This W68 login is not linked to a valid customer ID.');

        return [$account, $loginId, $customerId];
    }
}
