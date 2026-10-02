<!DOCTYPE html>
<html lang="en" data-auth-mode="{{ session('auth_mode', old('auth_mode', 'login')) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>W68 Special Store</title>
<link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20261002-canva-v2">
<script>
(function () {
    var ua = navigator.userAgent || '';
    var isHandheld =
        /iPhone|iPad|iPod|Android|Mobile|Tablet/i.test(ua) ||
        (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1);

    if (isHandheld) {
        document.documentElement.classList.add('w68-handheld');
    }
})();
</script>
<script src="{{ asset('js/w68-auth.js') }}?v=20261002-canva-v2" defer></script>
</head>
<body>
@php
    $showOtpModal = (bool) ($otpRequired ?? false)
        || session('otp_required')
        || $errors->has('otp')
        || in_array((string) session('w68_otp_purpose', ''), ['login', 'register', 'forgot'], true);

    $visibleOtpPurpose = (string) ($otpPurpose ?? session('otp_purpose', session('w68_otp_purpose', 'login')));
    $visibleOtpEmail = (string) ($otpEmail ?? session('otp_email', ''));
    $otpExpiresAt = (int) session('w68_otp_expires_at', 0);
    $serverNow = now()->timestamp;
@endphp

<main class="auth-stage">
    <div class="auth-shell">
        <section class="auth-form-column">
            <div class="auth-brand">
                <img class="auth-logo" src="{{ asset('images/sidebar_logo.png') }}" alt="W68">
                <div class="auth-brand-line">
                    <span>W68 SPECIAL STORE</span>
                    <svg class="brand-cart-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="10" cy="19" r="1.3" fill="currentColor"/>
                        <circle cx="18" cy="19" r="1.3" fill="currentColor"/>
                    </svg>
                </div>
            </div>

            @if (session('status'))
                <div class="auth-message auth-message-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any() && !$errors->has('otp'))
                <div class="auth-message auth-message-error">{{ $errors->first() }}</div>
            @endif

            <section class="auth-panel" data-auth-panel="login">
                <h1 class="auth-title">LOGIN</h1>

                <form method="POST" action="{{ route('login.attempt') }}" class="auth-form auth-form-box">
                    @csrf
                    <input type="hidden" name="auth_mode" value="login">

                    <div class="auth-field">
                        <label for="email">email:</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                    </div>

                    <div class="auth-field">
                        <label for="password">Password:</label>
                        <div class="password-wrap">
                            <input id="password" type="password" name="password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-controls="password">SHOW</button>
                        </div>
                    </div>

                    <button type="button" class="forgot-link" data-forgot-password>forgot password?</button>

                    <label class="remember-row">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>remember me?</span>
                    </label>

                    <div class="auth-actions">
                        <button type="submit" class="auth-button auth-button-primary">LOGIN</button>
                        <button type="button" class="auth-button auth-button-secondary" data-auth-switch="register">REGISTER</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('password.forgot') }}" data-forgot-password-form hidden>
                    @csrf
                    <input type="hidden" name="email" data-forgot-password-email>
                </form>
            </section>

            <section class="auth-panel" data-auth-panel="register" hidden>
                <h1 class="auth-title">REGISTER</h1>

                <div class="mobile-register-brand" aria-hidden="true">
                    <span>W68 SPECIAL STORE</span>
                    <svg class="brand-cart-icon" viewBox="0 0 24 24">
                        <path d="M3 4h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 7H7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="10" cy="19" r="1.3" fill="currentColor"/>
                        <circle cx="18" cy="19" r="1.3" fill="currentColor"/>
                    </svg>
                </div>

                <form method="POST" action="{{ route('register.attempt') }}" class="auth-form auth-form-box register-form-box">
                    @csrf
                    <input type="hidden" name="auth_mode" value="register">

                    <div class="register-grid">
                        <div class="auth-field">
                            <label for="username">User Name</label>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" autocomplete="username" required>
                        </div>

                        <div class="auth-field">
                            <label for="register_email">email:</label>
                            <input id="register_email" type="email" name="register_email" value="{{ old('register_email') }}" autocomplete="email" required>
                        </div>

                        <div class="auth-field">
                            <label for="register_password">Password:</label>
                            <div class="password-wrap">
                                <input id="register_password" type="password" name="register_password" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="register_password">SHOW</button>
                            </div>
                        </div>

                        <div class="auth-field">
                            <label for="register_password_confirmation">Re Type Password</label>
                            <div class="password-wrap">
                                <input id="register_password_confirmation" type="password" name="register_password_confirmation" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="register_password_confirmation">SHOW</button>
                            </div>
                        </div>
                    </div>

                    <div class="auth-actions">
                        <button type="submit" class="auth-button auth-button-primary">REGISTER</button>
                        <button type="button" class="auth-button auth-button-secondary" data-auth-switch="login">GO BACK TO LOGIN</button>
                    </div>
                </form>
            </section>
        </section>

        <aside class="auth-art-panel" aria-hidden="true">
            <img src="{{ asset('images/Shopping Cart of Automotive Parts.png') }}" alt="" class="auth-art">
        </aside>
    </div>
</main>

<div
    class="otp-modal{{ $showOtpModal ? ' is-open' : '' }}"
    data-otp-modal
    data-otp-open="{{ $showOtpModal ? 'true' : 'false' }}"
    data-otp-expires-at="{{ $otpExpiresAt }}"
    data-server-now="{{ $serverNow }}"
    @if (!$showOtpModal) hidden @endif
    aria-hidden="{{ $showOtpModal ? 'false' : 'true' }}"
>
    <div class="otp-backdrop" data-otp-cancel></div>

    <section class="otp-card" role="dialog" aria-modal="true" aria-labelledby="otp-title">
        <div class="otp-heading-band">
            <h2 id="otp-title">OTP CODE</h2>
            <p>One Time Password will be sent to your registered email. Please carefully check the email from W68 Auto Parts.</p>
        </div>

        @if ($visibleOtpEmail !== '')
            <div class="otp-email">{{ $visibleOtpEmail }}</div>
        @endif

        @if ($errors->has('otp'))
            <div class="auth-message auth-message-error otp-error">{{ $errors->first('otp') }}</div>
        @endif

        <form method="POST" action="{{ route('otp.verify') }}" class="otp-form">
            @csrf
            <div class="otp-countdown">expired within: <strong data-otp-countdown>--:--</strong></div>
            <input id="otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required data-otp-input>

            <div class="otp-actions" data-otp-active-actions>
                <button type="button" class="otp-button otp-button-cancel" data-otp-cancel>CANCEL</button>
                <button type="submit" class="otp-button otp-button-primary">VERIFY</button>
            </div>
        </form>

        <div class="otp-actions" data-otp-expired-actions hidden>
            <button type="button" class="otp-button otp-button-cancel" data-otp-cancel>CANCEL</button>
            <form method="POST" action="{{ route('otp.resend') }}" class="otp-resend-form">
                @csrf
                <button type="submit" class="otp-button otp-button-primary">RESEND</button>
            </form>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var forgotButton = document.querySelector('[data-forgot-password]');
    var forgotForm = document.querySelector('[data-forgot-password-form]');
    var forgotEmail = document.querySelector('[data-forgot-password-email]');
    var loginEmail = document.getElementById('email');

    if (!forgotButton || !forgotForm || !forgotEmail || !loginEmail) {
        return;
    }

    forgotButton.addEventListener('click', function () {
        if (!loginEmail.checkValidity()) {
            loginEmail.reportValidity();
            loginEmail.focus();
            return;
        }

        forgotEmail.value = loginEmail.value.trim();
        forgotForm.submit();
    });
});
</script>
</body>
</html>