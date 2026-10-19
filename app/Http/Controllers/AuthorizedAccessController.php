<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class AuthorizedAccessController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse|View
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return $this->denied('This authorization link is invalid.');
        }

        try {
            if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
                return $this->denied('Portal authorization is not configured yet. Please contact W68.');
            }

            $authorization = DB::connection('masterlist')
                ->table('customer_portal_authorizations')
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if (!$authorization) {
                return $this->denied('This authorization link was deleted or is invalid.');
            }

            if (now()->greaterThanOrEqualTo($authorization->expires_at)) {
                return $this->denied('This authorization link has expired. Please request a new link or QR code from W68.');
            }

            $customerExists = DB::connection('masterlist')
                ->table('customers')
                ->where('id', $authorization->customer_id)
                ->exists();

            if (!$customerExists) {
                return $this->denied('The customer assigned to this authorization no longer exists.');
            }

            /*
             * W68_AUTH_LINK_STALE_SYSTEM_SESSION_RECOVERY_20261019
             * The main W68 system database can be reprovisioned independently
             * from the Pricelist deployment. A browser may still carry an old
             * authenticated session whose account can no longer be loaded from
             * the previous system DB. Do not let that stale auth state turn a
             * valid generated authorization link into an HTTP 500.
             */
            $authenticatedLoginId = $this->safeAuthenticatedLoginId($request);

            if ($authenticatedLoginId !== null) {
                $linkedAccount = null;

                if (Schema::connection('system')->hasTable('customer_portal_accounts')) {
                    $linkedAccount = DB::connection('system')
                        ->table('customer_portal_accounts')
                        ->where('login_id', $authenticatedLoginId)
                        ->first();
                }

                if (!$linkedAccount) {
                    /*
                     * W68_AUTH_LINK_REPROVISIONED_ACCOUNT_RECOVERY_20261019
                     *
                     * After core4_system_proposal is reprovisioned/restored, a
                     * browser can still carry an authenticated login session
                     * whose permanent customer_portal_accounts row no longer
                     * exists in the restored database. If that stale login is
                     * allowed to survive this request, portal.access will reject
                     * /login even though the customer just opened a VALID newly
                     * generated authorization link.
                     *
                     * Treat an authenticated-but-unlinked login as stale here
                     * and continue the valid authorization flow as a guest.
                     */
                    Auth::logout();
                    $request->session()->regenerate();
                } elseif (
                    (int) $linkedAccount->customer_id !== (int) $authorization->customer_id
                ) {
                    /*
                     * W68_AUTH_LINK_ACCOUNT_OWNER_GUARD_20261006
                     * A logged-in account must never carry an authorization
                     * belonging to another customer.
                     */
                    Auth::logout();
                    $request->session()->regenerate();
                } else {
                    /*
                     * A newly generated token for the SAME customer replaces
                     * the old/expired authorization reference while keeping the
                     * permanent account-to-customer ownership unchanged.
                     */
                    DB::connection('system')
                        ->table('customer_portal_accounts')
                        ->where('id', $linkedAccount->id)
                        ->update([
                            'authorization_id' => (int) $authorization->id,
                            'updated_at' => now(),
                        ]);
                }
            }

            $request->session()->put([
                'w68_customer_authorization_id' => (int) $authorization->id,
                'w68_customer_id' => (int) $authorization->customer_id,
            ]);

            $request->session()->forget([
                'w68_otp_purpose',
                'w68_otp_expires_at',
                'w68_otp_attempts',
                'w68_otp_last_sent_at',
                'w68_pending_login_id',
                'w68_pending_login_remember',
                'w68_pending_registration_id',
                'w68_pending_customer_id',
                'w68_pending_authorization_id',
            ]);

            return redirect()
                ->route('login')
                ->with('status', 'Customer authorization accepted. You may now log in or register.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->denied('The W68 authorization service is temporarily unavailable. Please try again.');
        }
    }

    private function safeAuthenticatedLoginId(Request $request): ?int
    {
        try {
            if (!Auth::check()) {
                return null;
            }

            $loginId = (int) Auth::id();

            return $loginId > 0 ? $loginId : null;
        } catch (Throwable $exception) {
            report($exception);

            /*
             * Clear only Laravel's authentication key. Keep the current
             * authorization/customer session so this valid link can continue
             * as a guest and reach the login/registration page.
             */
            try {
                $request->session()->forget(Auth::guard()->getName());
            } catch (Throwable $sessionException) {
                report($sessionException);
            }

            return null;
        }
    }

    private function denied(string $message): View
    {
        return view('auth.authorization-required', [
            'authorizationMessage' => $message,
        ]);
    }
}
