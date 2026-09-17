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

            if (Auth::check()) {
                $linkedCustomerId = null;

                if (Schema::connection('system')->hasTable('customer_portal_accounts')) {
                    $linkedCustomerId = DB::connection('system')
                        ->table('customer_portal_accounts')
                        ->where('login_id', Auth::id())
                        ->value('customer_id');
                }

                if ($linkedCustomerId !== null && (int) $linkedCustomerId !== (int) $authorization->customer_id) {
                    Auth::logout();
                    $request->session()->regenerate();
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

    private function denied(string $message): View
    {
        return view('auth.authorization-required', [
            'authorizationMessage' => $message,
        ]);
    }
}
