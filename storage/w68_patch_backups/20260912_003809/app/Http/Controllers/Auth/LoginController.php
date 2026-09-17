<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\W68OtpService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class LoginController extends Controller
{
    public function __construct(
        private readonly W68OtpService $otpService,
    ) {
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (!$this->authTablesReady()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'W68 authentication tables are not installed in core4_system_proposal yet.',
                ]);
        }

        try {
            $user = User::query()
                ->where('email', mb_strtolower(trim($credentials['email'])))
                ->first();
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The core4_system_proposal login database is not available right now.',
                ]);
        }

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The email or password is incorrect.',
                ]);
        }

        try {
            $this->otpService->send($user->email, 'login');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The login OTP could not be sent. Please check the mail configuration.',
                ]);
        }

        $request->session()->put([
            'w68_otp_purpose' => 'login',
            'w68_otp_email' => $user->email,
            'w68_pending_login_user_id' => $user->id,
            'w68_pending_login_remember' => $request->boolean('remember'),
        ]);

        return redirect()
            ->route('login')
            ->with('otp_required', true)
            ->with('otp_purpose', 'login')
            ->with('otp_email', $user->email)
            ->with('status', 'We sent a 6-digit login OTP to your email.');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:100'],
            'register_email' => ['required', 'email', 'max:255'],
            'register_password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'register_password.confirmed' => 'The password and retype password do not match.',
        ]);

        if (!$this->authTablesReady()) {
            return back()
                ->withInput($request->except(['register_password', 'register_password_confirmation']))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => 'W68 authentication tables are not installed in core4_system_proposal yet.',
                ]);
        }

        $username = trim($validated['username']);
        $email = mb_strtolower(trim($validated['register_email']));

        try {
            $duplicateEmail = User::query()->where('email', $email)->exists();
            $duplicateUsername = User::query()->where('username', $username)->exists();
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['register_password', 'register_password_confirmation']))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => 'The core4_system_proposal registration database is not available right now.',
                ]);
        }

        if ($duplicateEmail) {
            return back()
                ->withInput($request->except(['register_password', 'register_password_confirmation']))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => 'That email is already registered.',
                ]);
        }

        if ($duplicateUsername) {
            return back()
                ->withInput($request->except(['register_password', 'register_password_confirmation']))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'username' => 'That username is already in use.',
                ]);
        }

        $request->session()->put('w68_pending_registration', [
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($validated['register_password']),
        ]);

        try {
            $this->otpService->send($email, 'register');
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget('w68_pending_registration');

            return back()
                ->withInput($request->except(['register_password', 'register_password_confirmation']))
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'The registration OTP could not be sent. Please check the mail configuration.',
                ]);
        }

        $request->session()->put([
            'w68_otp_purpose' => 'register',
            'w68_otp_email' => $email,
        ]);

        return redirect()
            ->route('login')
            ->with('auth_mode', 'register')
            ->with('otp_required', true)
            ->with('otp_purpose', 'register')
            ->with('otp_email', $email)
            ->with('status', 'We sent a 6-digit registration OTP to your email.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $purpose = (string) $request->session()->get('w68_otp_purpose', '');
        $email = (string) $request->session()->get('w68_otp_email', '');

        if (!in_array($purpose, ['register', 'login'], true) || $email === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your OTP session has expired. Please start again.',
                ]);
        }

        try {
            $this->otpService->verify($email, $purpose, $validated['otp']);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('login')
                ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
                ->with('otp_required', true)
                ->with('otp_purpose', $purpose)
                ->with('otp_email', $email)
                ->withErrors([
                    'otp' => $exception->getMessage(),
                ]);
        }

        if ($purpose === 'register') {
            return $this->completeRegistration($request, $email);
        }

        return $this->completeLogin($request, $email);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $purpose = (string) $request->session()->get('w68_otp_purpose', '');
        $email = (string) $request->session()->get('w68_otp_email', '');

        if (!in_array($purpose, ['register', 'login'], true) || $email === '') {
            return redirect()
                ->route('login')
                ->withErrors([
                    'otp' => 'Your OTP session has expired. Please start again.',
                ]);
        }

        try {
            $this->otpService->send($email, $purpose);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->with('auth_mode', $purpose === 'register' ? 'register' : 'login')
                ->with('otp_required', true)
                ->with('otp_purpose', $purpose)
                ->with('otp_email', $email)
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
            ->with('otp_email', $email)
            ->with('status', 'A new OTP was sent to your email.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completeRegistration(Request $request, string $email): RedirectResponse
    {
        $pending = $request->session()->get('w68_pending_registration');

        if (
            !is_array($pending)
            || ($pending['email'] ?? null) !== $email
            || empty($pending['username'])
            || empty($pending['password'])
        ) {
            $this->clearOtpSession($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'register_email' => 'Your registration session expired. Please register again.',
                ]);
        }

        try {
            $user = User::query()->create([
                'username' => $pending['username'],
                'email' => $pending['email'],
                'password' => $pending['password'],
                'email_verified_at' => now(),
            ]);
        } catch (QueryException $exception) {
            report($exception);
            $this->clearOtpSession($request);

            return redirect()
                ->route('login')
                ->with('auth_mode', 'register')
                ->withErrors([
                    'register_email' => 'That username or email is already registered.',
                ]);
        }

        $this->clearOtpSession($request);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('storefront')
            ->with('status', 'Registration completed successfully.');
    }

    private function completeLogin(Request $request, string $email): RedirectResponse
    {
        $userId = (int) $request->session()->get('w68_pending_login_user_id', 0);
        $remember = (bool) $request->session()->get('w68_pending_login_remember', false);

        $user = User::query()
            ->whereKey($userId)
            ->where('email', $email)
            ->first();

        if (!$user) {
            $this->clearOtpSession($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your login session expired. Please log in again.',
                ]);
        }

        $this->clearOtpSession($request);
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('storefront'));
    }

    private function clearOtpSession(Request $request): void
    {
        $request->session()->forget([
            'w68_otp_purpose',
            'w68_otp_email',
            'w68_pending_login_user_id',
            'w68_pending_login_remember',
            'w68_pending_registration',
        ]);
    }

    private function authTablesReady(): bool
    {
        try {
            return Schema::connection('system')->hasTable('w68_users')
                && Schema::connection('system')->hasTable('w68_auth_otps');
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
