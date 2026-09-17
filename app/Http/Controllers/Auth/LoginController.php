<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class LoginController extends Controller
{
    private const OTP_EXPIRES_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_RESEND_SECONDS = 60;
    private const PASSWORD_RESET_EXPIRES_MINUTES = 15;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        /*
         * Do not rely only on the one-request otp_required flash value.
         * Mobile Safari/iPad can restore or reload the login page while the
         * pending OTP session is still valid, which used to hide the modal.
         * The pending auth keys are ordinary session values and therefore are
         * the reliable source of truth until verifyOtp()/clearPendingAuth().
         */
        $otpPurpose = (string) $request->session()->get('w68_otp_purpose', '');
        $pendingAccountId = match ($otpPurpose) {
            'register' => (int) $request->session()->get('w68_pending_registration_id', 0),
            'forgot' => (int) $request->session()->get('w68_pending_password_reset_id', 0),
            default => (int) $request->session()->get('w68_pending_login_id', 0),
        };

        $otpRequired = in_array($otpPurpose, ['login', 'register', 'forgot'], true)
            && $pendingAccountId > 0;

        $otpEmail = (string) session('otp_email', '');

        if ($otpRequired && $otpEmail === '') {
            try {
                $otpEmail = (string) (LoginAccount::query()->find($pendingAccountId)?->Email ?? '');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('auth.login', [
            'otpRequired' => $otpRequired,
            'otpPurpose' => $otpPurpose,
            'otpEmail' => $otpEmail,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (!$this->existingAuthStructureIsReady()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The existing core4_system_proposal.logins authentication structure is not available.',
                ]);
        }

        $email = mb_strtolower(trim($validated['email']));

        try {
            $account = LoginAccount::query()
                ->whereRaw('LOWER(Email) = ?', [$email])
                ->where('account_type', 5)
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The core4_system_proposal database is not available right now.',
                ]);
        }

        if (!$account || !$this->passwordMatches($validated['password'], (string) $account->Password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The email or password is incorrect.',
                ]);
        }

        try {
            $portal = $this->portalContextForLogin($request, (int) $account->login_ID);
            $this->assertAccountCanUseCustomer((int) $account->login_ID, $portal['customer_id']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $exception->getMessage(),
                ]);
        }

        try {
            $this->issueOtp($request, $account, 'login');
        } catch (Throwable $exception) {
            report($exception);

            $account->OTP_CODE = null;
            $account->save();

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : $this->friendlyMailError($exception, 'login'),
                ]);
        }

        $request->session()->put([
            'w68_pending_login_id' => $account->login_ID,
            'w68_pending_login_remember' => $request->boolean('remember'),
            'w68_pending_customer_id' => $portal['customer_id'],
            'w68_pending_authorization_id' => $portal['authorization_id'],
        ]);

        return redirect()
            ->route('login')
            ->with('otp_required', true)
            ->with('otp_purpose', 'login')
            ->with('otp_email', $account->Email)
            ->with('status', 'A 6-digit login OTP was sent to your email.');
    }

    public function forgotPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        if (!$this->existingAuthStructureIsReady()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The existing core4_system_proposal.logins authentication structure is not available.',
                ]);
        }

        $email = mb_strtolower(trim($validated['email']));

        try {
            $account = LoginAccount::query()
                ->whereRaw('LOWER(Email) = ?', [$email])
                ->where('account_type', 5)
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The core4_system_proposal database is not available right now.',
                ]);
        }

        if (!$account) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'No W68 customer account was found for that email.',
                ]);
        }

        try {
            $portal = $this->portalContextForLogin($request, (int) $account->login_ID);
            $currentCustomerId = (int) $request->session()->get('w68_customer_id', 0);

            if (!(bool) ($portal['linked_account'] ?? false)) {
                throw new RuntimeException('This account is not yet linked to a W68 customer. Please complete registration first.');
            }

            if ($currentCustomerId < 1 || $currentCustomerId !== (int) $portal['customer_id']) {
                throw new RuntimeException('This email is not linked to the W68 customer opened by this authorization link.');
            }

            $this->assertAccountCanUseCustomer((int) $account->login_ID, (int) $portal['customer_id']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $exception->getMessage(),
                ]);
        }

        try {
            $this->issueOtp($request, $account, 'forgot');
        } catch (Throwable $exception) {
            report($exception);

            $account->OTP_CODE = null;
            $account->save();

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : $this->friendlyMailError($exception, 'password reset'),
                ]);
        }

        $request->session()->put([
            'w68_pending_password_reset_id' => $account->login_ID,
            'w68_pending_customer_id' => (int) $portal['customer_id'],
            'w68_pending_authorization_id' => (int) ($portal['authorization_id'] ?? 0),
        ]);

        return redirect()
            ->route('login')
            ->with('otp_required', true)
            ->with('otp_purpose', 'forgot')
            ->with('otp_email', $account->Email)
            ->with('status', 'A 6-digit password reset OTP was sent to your email.');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:255'],
            'register_email' => ['required', 'email', 'max:255'],
            'register_password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'register_password.confirmed' => 'The password and retype password do not match.',
        ]);

        if (!$this->existingAuthStructureIsReady()) {
            return back()
                ->withInput($request->except([
                    'register_password',
                    'register_password_confirmation',
                ]))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => 'The existing core4_system_proposal.logins authentication structure is not available.',
                ]);
        }

        $username = trim($validated['username']);
        $email = mb_strtolower(trim($validated['register_email']));

        try {
            $portal = $this->portalContext($request);
            $this->assertCustomerCanRegister($portal['customer_id']);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->except([
                    'register_password',
                    'register_password_confirmation',
                ]))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => $exception->getMessage(),
                ]);
        }

        try {
            $sameUsername = LoginAccount::query()
                ->where('User_ID', $username)
                ->first();

            $sameEmail = LoginAccount::query()
                ->whereRaw('LOWER(Email) = ?', [$email])
                ->first();

            $retryAccount = null;

            /*
             * If the immediately previous registration attempt created
             * account_type = 5 but SMTP failed, allow the same user to retry
             * instead of trapping them behind a duplicate-email error.
             *
             * This retry is only allowed when username + email point to the
             * same account and the submitted password matches that row.
             */
            if (
                $sameUsername
                && $sameEmail
                && (int) $sameUsername->login_ID === (int) $sameEmail->login_ID
                && (int) $sameEmail->account_type === 5
                && $this->passwordMatches(
                    $validated['register_password'],
                    (string) $sameEmail->Password
                )
            ) {
                $retryAccount = $sameEmail;
            }

            if ($sameUsername && !$retryAccount) {
                return back()
                    ->withInput($request->except([
                        'register_password',
                        'register_password_confirmation',
                    ]))
                    ->with('auth_mode', 'register')
                    ->withErrors([
                        'username' => 'That username is already in use.',
                    ]);
            }

            if ($sameEmail && !$retryAccount) {
                return back()
                    ->withInput($request->except([
                        'register_password',
                        'register_password_confirmation',
                    ]))
                    ->with('auth_mode', 'register')
                    ->withErrors([
                        'register_email' => 'That email is already registered.',
                    ]);
            }

            $accountWasCreatedNow = false;

            if ($retryAccount) {
                $account = $retryAccount;
                $account->OTP_CODE = null;
                $account->save();
            } else {
                $account = new LoginAccount();

                // W68 customer accounts are account_type = 5 immediately.
                $account->account_type = 5;
                $account->User_ID = $username;
                $account->Email = $email;
                $account->Password = Hash::make($validated['register_password']);
                $account->User_First_Name = $username;
                $account->User_Middle_Name = null;
                $account->User_Last_Name = 'W68 Customer';
                $account->Gender = 'N/A';
                $account->save();

                $accountWasCreatedNow = true;
            }

            try {
                $this->issueOtp($request, $account, 'register');
            } catch (Throwable $mailException) {
                /*
                 * Do not leave a newly-created unusable registration row if
                 * Gmail/SMTP fails. Existing/retry rows are kept intact.
                 */
                if ($accountWasCreatedNow) {
                    $account->delete();
                } else {
                    $account->OTP_CODE = null;
                    $account->save();
                }

                throw $mailException;
            }
        } catch (Throwable $exception) {
            report($exception);

            if (isset($account) && $account instanceof LoginAccount) {
                try {
                    $account->OTP_CODE = null;
                    $account->save();
                } catch (Throwable) {
                    // Keep the original exception as the useful failure.
                }
            }

            return back()
                ->withInput($request->except([
                    'register_password',
                    'register_password_confirmation',
                ]))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : ($exception instanceof TransportExceptionInterface
                            ? $this->friendlyMailError($exception, 'registration')
                            : 'Registration could not be completed. Check storage/logs/laravel.log for the exact application error.'),
                ]);
        }

        $request->session()->put([
            'w68_pending_registration_id' => $account->login_ID,
            'w68_pending_customer_id' => $portal['customer_id'],
            'w68_pending_authorization_id' => $portal['authorization_id'],
        ]);

        return redirect()
            ->route('login')
            ->with('auth_mode', 'register')
            ->with('otp_required', true)
            ->with('otp_purpose', 'register')
            ->with('otp_email', $account->Email)
            ->with('status', 'A 6-digit registration OTP was sent to your email.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $purpose = (string) $request->session()->get('w68_otp_purpose', '');

        if (!in_array($purpose, ['login', 'register', 'forgot'], true)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your OTP session expired. Please start again.',
                ]);
        }

        $accountId = match ($purpose) {
            'register' => (int) $request->session()->get('w68_pending_registration_id', 0),
            'forgot' => (int) $request->session()->get('w68_pending_password_reset_id', 0),
            default => (int) $request->session()->get('w68_pending_login_id', 0),
        };

        $account = LoginAccount::query()->find($accountId);

        if (!$account) {
            $this->clearPendingAuth($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'The OTP account could not be found. Please start again.',
                ]);
        }

        try {
            $this->assertOtpIsValid($request, $account, $validated['otp']);

            $portal = in_array($purpose, ['login', 'forgot'], true)
                ? $this->portalContextForLogin($request, (int) $account->login_ID)
                : $this->portalContext($request);

            $pendingCustomerId = (int) $request->session()->get('w68_pending_customer_id', 0);
            $pendingAuthorizationId = (int) $request->session()->get('w68_pending_authorization_id', 0);

            if ($pendingCustomerId !== (int) $portal['customer_id']) {
                throw new RuntimeException('The customer authorization changed during OTP verification. Please start again.');
            }

            if ($purpose === 'forgot') {
                $currentCustomerId = (int) $request->session()->get('w68_customer_id', 0);

                if (!(bool) ($portal['linked_account'] ?? false)) {
                    throw new RuntimeException('This account is not linked to a W68 customer.');
                }

                if ($currentCustomerId < 1 || $currentCustomerId !== (int) $portal['customer_id']) {
                    throw new RuntimeException('The W68 customer authorization changed during password reset. Please start again.');
                }

                $this->assertAccountCanUseCustomer(
                    (int) $account->login_ID,
                    (int) $portal['customer_id']
                );
            } else {
                /*
                 * First-time/registration authorization must still be the same QR
                 * authorization that began the OTP flow. For an already-linked
                 * login, the permanent customer_portal_accounts row is the source
                 * of truth and its original authorization may already be expired.
                 */
                if (!(bool) ($portal['linked_account'] ?? false)) {
                    if ($pendingAuthorizationId !== (int) $portal['authorization_id']) {
                        throw new RuntimeException('The customer authorization changed during OTP verification. Please start again from the authorization link.');
                    }

                    $this->linkPortalAccount(
                        (int) $account->login_ID,
                        (int) $portal['customer_id'],
                        (int) $portal['authorization_id']
                    );
                } else {
                    $this->assertAccountCanUseCustomer(
                        (int) $account->login_ID,
                        (int) $portal['customer_id']
                    );
                }
            }
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('login')
                ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
                ->with('otp_required', true)
                ->with('otp_purpose', $purpose)
                ->with('otp_email', $account->Email)
                ->withErrors([
                    'otp' => $exception->getMessage(),
                ]);
        }

        $account->OTP_CODE = null;

        // Registration and login accounts are W68 account_type = 5.
        $account->account_type = 5;
        $account->save();

        if ($purpose === 'forgot') {
            $resetLoginId = (int) $account->login_ID;
            $resetCustomerId = (int) $pendingCustomerId;

            $this->clearPendingAuth($request);
            $request->session()->put([
                'w68_password_reset_login_id' => $resetLoginId,
                'w68_password_reset_customer_id' => $resetCustomerId,
                'w68_password_reset_expires_at' => now()
                    ->addMinutes(self::PASSWORD_RESET_EXPIRES_MINUTES)
                    ->timestamp,
            ]);
            $request->session()->regenerateToken();

            return redirect()
                ->route('password.reset.form')
                ->with('status', 'OTP verified. Set your new password below.');
        }

        $this->clearPendingAuth($request);

        /*
         * logins does not have Laravel's remember_token column, therefore
         * use the normal secure session login. The Remember Me checkbox is
         * intentionally not allowed to invent/alter a database column.
         */
        Auth::login($account, false);
        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with('status', $purpose === 'register'
                ? 'Registration completed successfully.'
                : 'Login verified successfully.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $purpose = (string) $request->session()->get('w68_otp_purpose', '');

        if (!in_array($purpose, ['login', 'register', 'forgot'], true)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your OTP session expired. Please start again.',
                ]);
        }

        $lastSent = (int) $request->session()->get('w68_otp_last_sent_at', 0);
        $elapsed = time() - $lastSent;

        if ($lastSent > 0 && $elapsed < self::OTP_RESEND_SECONDS) {
            $wait = self::OTP_RESEND_SECONDS - $elapsed;

            return redirect()
                ->route('login')
                ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
                ->with('otp_required', true)
                ->with('otp_purpose', $purpose)
                ->withErrors([
                    'otp' => "Please wait {$wait} second" . ($wait === 1 ? '' : 's') . ' before resending.',
                ]);
        }

        $accountId = match ($purpose) {
            'register' => (int) $request->session()->get('w68_pending_registration_id', 0),
            'forgot' => (int) $request->session()->get('w68_pending_password_reset_id', 0),
            default => (int) $request->session()->get('w68_pending_login_id', 0),
        };

        $account = LoginAccount::query()->find($accountId);

        if (!$account || !$account->Email) {
            $this->clearPendingAuth($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'The OTP account could not be found. Please start again.',
                ]);
        }

        try {
            $this->issueOtp($request, $account, $purpose);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
                ->with('otp_required', true)
                ->with('otp_purpose', $purpose)
                ->with('otp_email', $account->Email)
                ->withErrors([
                    'otp' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The OTP could not be resent.',
                ]);
        }

        return redirect()
            ->route('login')
            ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
            ->with('otp_required', true)
            ->with('otp_purpose', $purpose)
            ->with('otp_email', $account->Email)
            ->with('status', 'A new OTP was sent to your email.');
    }

    public function showResetPassword(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        try {
            $account = $this->passwordResetAccount($request);
        } catch (RuntimeException $exception) {
            $this->clearPasswordReset($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $exception->getMessage(),
                ]);
        }

        return view('auth.reset-password', [
            'resetEmail' => (string) $account->Email,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'password.confirmed' => 'The new password and retype password do not match.',
        ]);

        try {
            $account = $this->passwordResetAccount($request);
        } catch (RuntimeException $exception) {
            $this->clearPasswordReset($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $exception->getMessage(),
                ]);
        }

        $account->Password = Hash::make($validated['password']);
        $account->OTP_CODE = null;
        $account->account_type = 5;
        $account->save();

        $this->clearPasswordReset($request);
        $this->clearPendingAuth($request);

        Auth::login($account, false);
        $request->session()->regenerate();

        return redirect()
            ->route('home')
            ->with('status', 'Password reset successfully. You are now logged in.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $authorizationId = (int) $request->session()->get('w68_customer_authorization_id', 0);
        $customerId = (int) $request->session()->get('w68_customer_id', 0);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($authorizationId > 0 && $customerId > 0) {
            $request->session()->put([
                'w68_customer_authorization_id' => $authorizationId,
                'w68_customer_id' => $customerId,
            ]);
        }

        return redirect()->route('login');
    }

    private function existingAuthStructureIsReady(): bool
    {
        try {
            return Schema::connection('system')->hasTable('logins')
                && Schema::connection('system')->hasColumn('logins', 'Email')
                && Schema::connection('system')->hasColumn('logins', 'Password')
                && Schema::connection('system')->hasColumn('logins', 'OTP_CODE');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function issueOtp(
        Request $request,
        LoginAccount $account,
        string $purpose
    ): void {
        if (!$account->Email) {
            throw new RuntimeException('This account does not have an email address.');
        }

        if ((string) config('mail.default') !== 'smtp') {
            throw new RuntimeException('SMTP email is not configured.');
        }

        $code = random_int(100000, 999999);
        $action = match ($purpose) {
            'register' => 'registration',
            'forgot' => 'password reset',
            default => 'login',
        };

        $account->OTP_CODE = $code;
        $account->save();

        try {
            $html = $this->otpEmailHtml($code, $action);

            Mail::html(
                $html,
                function ($message) use ($account, $action): void {
                    $message
                        ->from(
                            (string) config('mail.from.address'),
                            'W68 Autoparts & Service Center'
                        )
                        ->to($account->Email)
                        ->subject(
                            'W68 Autoparts & Service Center - '
                            . ucfirst($action)
                            . ' OTP'
                        );
                }
            );
        } catch (Throwable $exception) {
            $account->OTP_CODE = null;
            $account->save();

            throw $exception;
        }

        $request->session()->put([
            'w68_otp_purpose' => $purpose,
            'w68_otp_expires_at' => now()
                ->addMinutes(self::OTP_EXPIRES_MINUTES)
                ->timestamp,
            'w68_otp_attempts' => 0,
            'w68_otp_last_sent_at' => time(),
        ]);
    }

    private function otpEmailHtml(int $code, string $action): string
    {
        $safeAction = htmlspecialchars(
            ucfirst($action),
            ENT_QUOTES,
            'UTF-8'
        );

        $safeCode = htmlspecialchars(
            (string) $code,
            ENT_QUOTES,
            'UTF-8'
        );

        $minutes = self::OTP_EXPIRES_MINUTES;

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>W68 Autoparts &amp; Service Center OTP</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f4;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="width:100%;background:#f3f5f4;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 14px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                       style="width:100%;max-width:580px;background:#5f0b1c;border:4px solid #ffd633;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:34px 28px 14px;">
                            <div style="font-size:25px;line-height:1.2;font-weight:900;color:#ffd633;text-transform:uppercase;">
                                W68 Autoparts &amp; Service Center
                            </div>
                            <div style="margin-top:7px;font-size:13px;font-weight:700;color:#ffffff;">
                                {$safeAction} Verification
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:15px 28px 6px;">
                            <div style="font-size:13px;line-height:1.6;color:#ffffff;">
                                Use the following 6-digit one-time password to continue.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:18px 28px;">
                            <div style="display:inline-block;min-width:260px;padding:20px 24px;border:2px solid #ffd633;border-radius:12px;background:#4a0816;color:#ffd633;font-size:40px;line-height:1;font-weight:900;letter-spacing:10px;text-align:center;">
                                {$safeCode}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:8px 28px 32px;">
                            <div style="font-size:12px;line-height:1.6;color:#ffffff;">
                                This OTP expires in <strong style="color:#ffd633;">{$minutes} minutes</strong>.
                            </div>
                            <div style="margin-top:8px;font-size:11px;line-height:1.5;color:#f6dfe4;">
                                Do not share this OTP with anyone.
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="max-width:580px;margin-top:13px;font-size:10px;line-height:1.5;color:#6c756f;text-align:center;">
                    This message was sent by W68 Autoparts &amp; Service Center.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function assertOtpIsValid(
        Request $request,
        LoginAccount $account,
        string $submittedOtp
    ): void {
        $expiresAt = (int) $request->session()->get('w68_otp_expires_at', 0);
        $attempts = (int) $request->session()->get('w68_otp_attempts', 0);

        if ($expiresAt === 0 || !$account->OTP_CODE) {
            throw new RuntimeException('No active OTP was found. Please request a new OTP.');
        }

        if (time() > $expiresAt) {
            $account->OTP_CODE = null;
            $account->save();

            throw new RuntimeException('That OTP expired. Please request a new OTP.');
        }

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $account->OTP_CODE = null;
            $account->save();

            throw new RuntimeException('Too many incorrect OTP attempts. Please request a new OTP.');
        }

        if (!hash_equals((string) $account->OTP_CODE, trim($submittedOtp))) {
            $attempts++;

            $request->session()->put('w68_otp_attempts', $attempts);

            $remaining = max(0, self::OTP_MAX_ATTEMPTS - $attempts);

            throw new RuntimeException(
                'Incorrect OTP. '
                . ($remaining > 0
                    ? "{$remaining} attempt" . ($remaining === 1 ? '' : 's') . ' remaining.'
                    : 'Request a new OTP.')
            );
        }
    }

    private function friendlyMailError(
        Throwable $exception,
        string $purpose
    ): string {
        $message = mb_strtolower($exception->getMessage());

        if (
            str_contains($message, '535')
            || str_contains($message, 'username and password not accepted')
            || str_contains($message, 'authentication failed')
            || str_contains($message, 'badcredentials')
        ) {
            return 'Gmail rejected the SMTP login. Check the Gmail App Password used for W68 OTP.';
        }

        if (
            str_contains($message, 'certificate')
            || str_contains($message, 'peer certificate')
            || str_contains($message, 'ssl operation failed')
        ) {
            return 'Gmail TLS certificate verification failed on this XAMPP PC. The W68 mail configuration needs peer verification disabled for development.';
        }

        if (
            str_contains($message, 'timed out')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'failed to open stream')
        ) {
            return 'W68 could not connect to Gmail SMTP. Check internet/firewall access to smtp.gmail.com:587.';
        }

        return 'The ' . $purpose . ' OTP could not be sent through Gmail SMTP. Check storage/logs/laravel.log for the exact mail error.';
    }

    private function portalContextForLogin(Request $request, int $loginId): array
    {
        $this->assertPortalLinkTableReady();

        $link = DB::connection('system')
            ->table('customer_portal_accounts')
            ->where('login_id', $loginId)
            ->first();

        if ($link) {
            $customerId = (int) $link->customer_id;

            $customerExists = DB::connection('masterlist')
                ->table('customers')
                ->where('id', $customerId)
                ->exists();

            if (!$customerExists) {
                throw new RuntimeException('The W68 customer linked to this Pricelist account no longer exists.');
            }

            return [
                'authorization_id' => (int) ($link->authorization_id ?? 0),
                'customer_id' => $customerId,
                'linked_account' => true,
            ];
        }

        $portal = $this->portalContext($request);
        $portal['linked_account'] = false;

        return $portal;
    }

    private function portalContext(Request $request): array
    {
        $authorizationId = (int) $request->session()->get('w68_customer_authorization_id', 0);
        $customerId = (int) $request->session()->get('w68_customer_id', 0);

        if ($authorizationId < 1 || $customerId < 1) {
            throw new RuntimeException('A valid W68 customer authorization link or QR code is required.');
        }

        if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
            throw new RuntimeException('Customer portal authorization is not configured yet.');
        }

        $authorization = DB::connection('masterlist')
            ->table('customer_portal_authorizations')
            ->where('id', $authorizationId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$authorization) {
            throw new RuntimeException('This customer authorization was deleted or is invalid.');
        }

        if (now()->greaterThanOrEqualTo($authorization->expires_at)) {
            throw new RuntimeException('This customer authorization has expired. Please request a new link or QR code from W68.');
        }

        return [
            'authorization_id' => (int) $authorization->id,
            'customer_id' => (int) $authorization->customer_id,
            'linked_account' => false,
        ];
    }

    private function assertAccountCanUseCustomer(int $loginId, int $customerId): void
    {
        $this->assertPortalLinkTableReady();

        $byLogin = DB::connection('system')
            ->table('customer_portal_accounts')
            ->where('login_id', $loginId)
            ->first();

        if ($byLogin && (int) $byLogin->customer_id !== $customerId) {
            throw new RuntimeException('This Pricelist account belongs to a different W68 customer authorization.');
        }

        $byCustomer = DB::connection('system')
            ->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->first();

        if ($byCustomer && (int) $byCustomer->login_id !== $loginId) {
            throw new RuntimeException('This W68 customer is already connected to another Pricelist account. Please use that customer account.');
        }
    }

    private function assertCustomerCanRegister(int $customerId): void
    {
        $this->assertPortalLinkTableReady();

        $existing = DB::connection('system')
            ->table('customer_portal_accounts')
            ->where('customer_id', $customerId)
            ->first();

        if ($existing) {
            throw new RuntimeException('This W68 customer already has a Pricelist account. Please use Login instead of creating another account.');
        }
    }

    private function linkPortalAccount(int $loginId, int $customerId, int $authorizationId): void
    {
        $this->assertPortalLinkTableReady();

        DB::connection('system')->transaction(function () use ($loginId, $customerId, $authorizationId): void {
            $byLogin = DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('login_id', $loginId)
                ->lockForUpdate()
                ->first();

            $byCustomer = DB::connection('system')
                ->table('customer_portal_accounts')
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->first();

            if ($byLogin && (int) $byLogin->customer_id !== $customerId) {
                throw new RuntimeException('This Pricelist account is already linked to a different W68 customer.');
            }

            if ($byCustomer && (int) $byCustomer->login_id !== $loginId) {
                throw new RuntimeException('This W68 customer is already linked to another Pricelist account.');
            }

            if ($byLogin) {
                DB::connection('system')
                    ->table('customer_portal_accounts')
                    ->where('id', $byLogin->id)
                    ->update([
                        'authorization_id' => $authorizationId,
                        'linked_at' => $byLogin->linked_at ?: now(),
                        'updated_at' => now(),
                    ]);
                return;
            }

            DB::connection('system')
                ->table('customer_portal_accounts')
                ->insert([
                    'customer_id' => $customerId,
                    'login_id' => $loginId,
                    'authorization_id' => $authorizationId,
                    'linked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    private function assertPortalLinkTableReady(): void
    {
        if (!Schema::connection('system')->hasTable('customer_portal_accounts')) {
            throw new RuntimeException('Customer portal account linking is not configured yet. Run the W68 portal migration.');
        }
    }

    private function passwordMatches(string $plain, string $stored): bool
    {
        if (
            str_starts_with($stored, '$2y$')
            || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$2b$')
            || str_starts_with($stored, '$argon2')
        ) {
            return Hash::check($plain, $stored);
        }

        /*
         * Existing legacy core4_system_proposal.logins rows contain plain
         * passwords. Keep them readable without rewriting existing records.
         * Newly registered W68 accounts are always bcrypt-hashed.
         */
        return hash_equals($stored, $plain);
    }

    private function passwordResetAccount(Request $request): LoginAccount
    {
        $loginId = (int) $request->session()->get('w68_password_reset_login_id', 0);
        $customerId = (int) $request->session()->get('w68_password_reset_customer_id', 0);
        $expiresAt = (int) $request->session()->get('w68_password_reset_expires_at', 0);
        $currentCustomerId = (int) $request->session()->get('w68_customer_id', 0);

        if ($loginId < 1 || $customerId < 1 || $expiresAt < 1) {
            throw new RuntimeException('Your password reset session expired. Please request a new OTP.');
        }

        if (time() > $expiresAt) {
            throw new RuntimeException('Your password reset session expired. Please request a new OTP.');
        }

        if ($currentCustomerId < 1 || $currentCustomerId !== $customerId) {
            throw new RuntimeException('The W68 customer authorization changed. Please start the password reset again.');
        }

        $account = LoginAccount::query()
            ->where('account_type', 5)
            ->find($loginId);

        if (!$account) {
            throw new RuntimeException('The W68 customer account could not be found.');
        }

        $this->assertPortalLinkTableReady();

        $linked = DB::connection('system')
            ->table('customer_portal_accounts')
            ->where('login_id', $loginId)
            ->where('customer_id', $customerId)
            ->exists();

        if (!$linked) {
            throw new RuntimeException('This account is no longer linked to the authorized W68 customer.');
        }

        return $account;
    }

    private function clearPasswordReset(Request $request): void
    {
        $request->session()->forget([
            'w68_password_reset_login_id',
            'w68_password_reset_customer_id',
            'w68_password_reset_expires_at',
        ]);
    }

    private function clearPendingAuth(Request $request): void
    {
        $request->session()->forget([
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
        ]);
    }
}
