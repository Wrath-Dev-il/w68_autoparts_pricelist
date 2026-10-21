<?php

declare(strict_types=1);

// W68_CLEANUP_PORTAL_SIGNOFF_FROM_SALES_NOTES_20261021
//
// Preview:
//   php scripts/cleanup_portal_signoff_from_sales_notes.php
//
// Apply:
//   php scripts/cleanup_portal_signoff_from_sales_notes.php --apply
//
// Clears only Sales Note prepared_by / checked_by values that exactly match
// the portal-order sign-off values created by the accidental mirror behavior.
// Portal order values remain untouched.

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$apply = in_array('--apply', $argv ?? [], true);

echo "=== W68 SALES NOTE PORTAL SIGN-OFF CLEANUP ===\n";
echo "MODE=" . ($apply ? 'APPLY' : 'PREVIEW') . "\n";

$schema = Schema::connection('sales_order');

foreach (['sales_notes', 'w68_portal_orders'] as $table) {
    if (!$schema->hasTable($table)) {
        fwrite(STDERR, strtoupper($table) . "=MISSING\n");
        exit(2);
    }
}

foreach (['prepared_by', 'checked_by', 'sales_note_id'] as $column) {
    if (!$schema->hasColumn('w68_portal_orders', $column)) {
        fwrite(STDERR, "W68_PORTAL_ORDERS_COLUMN_{$column}=MISSING\n");
        exit(2);
    }
}

foreach (['prepared_by', 'checked_by', 'remarks'] as $column) {
    if (!$schema->hasColumn('sales_notes', $column)) {
        fwrite(STDERR, "SALES_NOTES_COLUMN_{$column}=MISSING\n");
        exit(2);
    }
}

$db = DB::connection('sales_order');
$db->getPdo();
echo "SALES_ORDER_CONNECTED=YES\n";

$matches = $db->table('sales_notes as sn')
    ->join('w68_portal_orders as po', 'po.sales_note_id', '=', 'sn.id')
    ->whereNotNull('po.prepared_by')
    ->whereNotNull('po.checked_by')
    ->whereRaw("TRIM(COALESCE(po.prepared_by, '')) <> ''")
    ->whereRaw("TRIM(COALESCE(po.checked_by, '')) <> ''")
    ->whereRaw("COALESCE(sn.prepared_by, '') = COALESCE(po.prepared_by, '')")
    ->whereRaw("COALESCE(sn.checked_by, '') = COALESCE(po.checked_by, '')")
    ->where('sn.remarks', 'like', 'W68 PRICELIST PORTAL ORDER%')
    ->orderBy('sn.id')
    ->get([
        'sn.id as sales_note_id',
        'sn.sales_number',
        'sn.prepared_by as sales_note_prepared_by',
        'sn.checked_by as sales_note_checked_by',
        'po.id as portal_order_id',
        'po.order_code',
        'po.prepared_by as portal_prepared_by',
        'po.checked_by as portal_checked_by',
    ]);

echo "MATCHED_SALES_NOTES={$matches->count()}\n";

foreach ($matches as $row) {
    echo "SALES_NOTE_ID={$row->sales_note_id}"
        . " | SALES_NUMBER=" . ($row->sales_number ?? '')
        . " | PORTAL_ORDER_ID={$row->portal_order_id}"
        . " | ORDER_CODE=" . ($row->order_code ?? '')
        . "\n";
}

if (!$apply) {
    echo "PREVIEW_COMPLETE=YES\n";
    exit(0);
}

if ($matches->isEmpty()) {
    echo "NOTHING_TO_CLEAN=YES\n";
    echo "SUCCESS=YES\n";
    exit(0);
}

$ids = $matches->pluck('sales_note_id')->map(fn ($id) => (int) $id)->all();

$updated = $db->table('sales_notes')
    ->whereIn('id', $ids)
    ->update([
        'prepared_by' => null,
        'checked_by' => null,
        'updated_at' => now(),
    ]);

echo "CLEARED_SALES_NOTES={$updated}\n";
echo "PORTAL_ORDER_SIGNOFFS_PRESERVED=YES\n";
echo "SUCCESS=YES\n";
