<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class LoginController extends Controller
{
    private const OTP_EXPIRES_MINUTES = 10;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_RESEND_SECONDS = 60;

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront');
        }

        return view('auth.login');
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
        ]);

        return redirect()
            ->route('login')
            ->with('otp_required', true)
            ->with('otp_purpose', 'login')
            ->with('otp_email', $account->Email)
            ->with('status', 'A 6-digit login OTP was sent to your email.');
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
                        : $this->friendlyMailError($exception, 'registration'),
                ]);
        }

        $request->session()->put('w68_pending_registration_id', $account->login_ID);

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

        if (!in_array($purpose, ['login', 'register'], true)) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your OTP session expired. Please start again.',
                ]);
        }

        $accountId = $purpose === 'register'
            ? (int) $request->session()->get('w68_pending_registration_id', 0)
            : (int) $request->session()->get('w68_pending_login_id', 0);

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

        $this->clearPendingAuth($request);

        /*
         * logins does not have Laravel's remember_token column, therefore
         * use the normal secure session login. The Remember Me checkbox is
         * intentionally not allowed to invent/alter a database column.
         */
        Auth::login($account, false);
        $request->session()->regenerate();

        return redirect()
            ->route('storefront')
            ->with('status', $purpose === 'register'
                ? 'Registration completed successfully.'
                : 'Login verified successfully.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $purpose = (string) $request->session()->get('w68_otp_purpose', '');

        if (!in_array($purpose, ['login', 'register'], true)) {
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

        $accountId = $purpose === 'register'
            ? (int) $request->session()->get('w68_pending_registration_id', 0)
            : (int) $request->session()->get('w68_pending_login_id', 0);

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

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
        $action = $purpose === 'register' ? 'registration' : 'login';

        $account->OTP_CODE = $code;
        $account->save();

        try {
            Mail::raw(
                "Your W68 Autoparts {$action} OTP is: {$code}\n\n"
                . 'This code expires in ' . self::OTP_EXPIRES_MINUTES . " minutes.\n"
                . 'Do not share this OTP with anyone.',
                function ($message) use ($account, $action): void {
                    $message
                        ->to($account->Email)
                        ->subject('W68 Autoparts ' . ucfirst($action) . ' OTP');
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
        ]);
    }
}
