<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureCustomerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * A customer authorization QR/link is only required while establishing
         * a new portal relationship. Once an account is linked in
         * customer_portal_accounts, the linked account remains valid even when
         * the original QR/link reaches expires_at.
         *
         * This prevents an already-linked customer from being logged out in the
         * middle of shopping or processing an order merely because the original
         * QR timer elapsed.
         */
        try {
            if (Auth::check()) {
                if (!Schema::connection('system')->hasTable('customer_portal_accounts')) {
                    return $this->deny($request, 'Customer portal account linking is not configured yet.');
                }

                $link = DB::connection('system')
                    ->table('customer_portal_accounts')
                    ->where('login_id', Auth::id())
                    ->first();

                if (!$link) {
                    return $this->deny($request, 'This Pricelist account is not linked to a W68 customer.');
                }

                $linkedCustomerId = (int) $link->customer_id;

                $customerExists = DB::connection('masterlist')
                    ->table('customers')
                    ->where('id', $linkedCustomerId)
                    ->exists();

                if (!$customerExists) {
                    return $this->deny($request, 'The customer linked to this Pricelist account no longer exists.');
                }

                // Rebuild the portal session from the permanent account link.
                // Do NOT re-check the old authorization expires_at here.
                $request->session()->put('w68_customer_id', $linkedCustomerId);

                $linkedAuthorizationId = (int) ($link->authorization_id ?? 0);
                if ($linkedAuthorizationId > 0) {
                    $request->session()->put('w68_customer_authorization_id', $linkedAuthorizationId);
                }

                return $next($request);
            }

            /*
             * Linked customers must be able to reach Login and complete login
             * OTP even after the original QR expires. LoginController will
             * resolve an existing customer_portal_accounts link by login_ID.
             * Registration still requires a fresh authorization.
             */
            if ($this->allowsLinkedAccountLoginWithoutFreshQr($request)) {
                return $next($request);
            }

            $authorizationId = (int) $request->session()->get('w68_customer_authorization_id', 0);
            $customerId = (int) $request->session()->get('w68_customer_id', 0);

            if ($authorizationId < 1 || $customerId < 1) {
                return $this->deny($request, 'Open the authorization link or scan the QR code supplied by W68 before accessing customer registration.');
            }

            if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
                return $this->deny($request, 'Portal authorization is not configured yet. Please contact W68.');
            }

            $authorization = DB::connection('masterlist')
                ->table('customer_portal_authorizations')
                ->where('id', $authorizationId)
                ->where('customer_id', $customerId)
                ->first();

            if (!$authorization) {
                return $this->deny($request, 'This customer authorization was deleted and is no longer valid.');
            }

            if ($authorization->expires_at && now()->greaterThanOrEqualTo($authorization->expires_at)) {
                return $this->deny($request, 'This customer authorization has expired. Please request a new link or QR code from W68.');
            }

            $customerExists = DB::connection('masterlist')
                ->table('customers')
                ->where('id', $customerId)
                ->exists();

            if (!$customerExists) {
                return $this->deny($request, 'The customer assigned to this authorization no longer exists.');
            }
        } catch (Throwable $exception) {
            report($exception);
            return $this->deny($request, 'The W68 authorization service is temporarily unavailable. Please try again.');
        }

        return $next($request);
    }

    private function allowsLinkedAccountLoginWithoutFreshQr(Request $request): bool
    {
        $route = $request->route();
        $routeName = $route ? (string) $route->getName() : '';

        return in_array($routeName, [
            'login',
            'login.attempt',
            'otp.verify',
            'otp.resend',
            'logout',
        ], true);
    }

    private function deny(Request $request, string $message): Response
    {
        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->forget([
            'w68_customer_authorization_id',
            'w68_customer_id',
            'w68_otp_purpose',
            'w68_otp_expires_at',
            'w68_otp_attempts',
            'w68_otp_last_sent_at',
            'w68_pending_login_id',
            'w68_pending_login_remember',
            'w68_pending_registration_id',
            'w68_pending_password_reset_id',
            'w68_pending_customer_id',
            'w68_pending_authorization_id',
            'w68_password_reset_login_id',
            'w68_password_reset_customer_id',
            'w68_password_reset_expires_at',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'authorization_required' => true,
            ], 403);
        }

        return response()->view('auth.authorization-required', [
            'authorizationMessage' => $message,
        ], 403);
    }
}
