<!DOCTYPE html>
<html lang="en" data-auth-mode="{{ session('auth_mode', old('auth_mode', 'login')) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>W68 Autoparts</title>

    <link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20260914-v60">

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

    <script src="{{ asset('js/w68-auth.js') }}?v=20260914-v60" defer></script>
</head>
<body>
    @php
        $showOtpModal = (bool) ($otpRequired ?? false)
            || session('otp_required')
            || $errors->has('otp')
            || in_array((string) session('w68_otp_purpose', ''), ['login', 'register'], true);
        $visibleOtpPurpose = (string) ($otpPurpose ?? session('otp_purpose', session('w68_otp_purpose', 'login')));
        $visibleOtpEmail = (string) ($otpEmail ?? session('otp_email', ''));
    @endphp

    <div class="w68-auth-loader" data-auth-loader aria-hidden="false">
        <svg class="w68-auth-loader-svg" viewBox="0 0 750 260" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="W68 loading">
            <defs>
                <linearGradient id="authLoaderMaroonGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#a31c3f" />
                    <stop offset="40%" stop-color="#800020" />
                    <stop offset="100%" stop-color="#4a0011" />
                </linearGradient>
                <linearGradient id="authLoaderGoldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FFF5C0" />
                    <stop offset="30%" stop-color="#FFD700" />
                    <stop offset="70%" stop-color="#D4AF37" />
                    <stop offset="100%" stop-color="#996515" />
                </linearGradient>
            </defs>
            <g class="w68-auth-loader-trace" stroke-linecap="round" stroke-linejoin="round">
                <path d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="#800020" stroke-width="16" fill="none" />
                <path d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="#d4af37" stroke-width="16" fill="none" />
                <path d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="#d4af37" stroke-width="16" fill="none" />
            </g>
            <g stroke-linecap="round" stroke-linejoin="round">
                <path data-auth-loader-path data-loader-color="#800020" class="auth-loader-maroon-glow" d="M 45 95 C 45 60, 65 55, 75 80 L 105 175 C 115 200, 130 200, 140 170 L 165 105 C 175 80, 185 80, 195 105 L 215 170 C 225 200, 240 200, 250 175 L 275 90 C 282 68, 298 75, 285 100 C 275 120, 290 120, 305 100" stroke="url(#authLoaderMaroonGrad)" stroke-width="16" fill="none" />
                <path data-auth-loader-path data-loader-color="#FFD700" class="auth-loader-gold-glow" d="M 430 70 C 390 70, 355 105, 345 150 C 338 185, 350 215, 385 218 C 420 220, 445 195, 445 165 C 445 135, 415 125, 380 132 C 352 138, 345 158, 345 165" stroke="url(#authLoaderGoldGrad)" stroke-width="16" fill="none" />
                <path data-auth-loader-path data-loader-color="#FFD700" class="auth-loader-gold-glow" d="M 585 135 C 550 120, 530 98, 530 78 C 530 52, 555 42, 580 42 C 610 42, 630 58, 630 80 C 630 102, 605 122, 570 135 C 525 150, 505 172, 505 198 C 505 225, 535 238, 575 238 C 620 238, 645 220, 645 192 C 645 160, 610 142, 570 135" stroke="url(#authLoaderGoldGrad)" stroke-width="16" fill="none" />
            </g>
            <g data-auth-loader-pen class="w68-auth-loader-pen">
                <circle data-auth-loader-aura cx="0" cy="0" r="14" fill="#FFD700" opacity="0.35" />
                <circle data-auth-loader-dot cx="0" cy="0" r="6" fill="#FFFFFF" stroke="#FFD700" stroke-width="2.5" />
                <circle data-auth-loader-sparkle-one cx="0" cy="0" r="2.5" fill="#FFF2B2" />
                <circle data-auth-loader-sparkle-two cx="0" cy="0" r="2" fill="#D4AF37" />
            </g>
        </svg>
    </div>
    <div class="login-page">
        <div class="login-card">
            <div class="auth-brand">
                <img
                    class="login-logo"
                    src="{{ asset('build/assets/images/sidebar_logo.png') }}"
                    alt="W68 Autoparts Logo"
                >

                <div>
                    <h1>W68 AUTOPARTS</h1>
                    <p class="subtitle">Online Pricelist</p>
                </div>
            </div>

            @if (session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if ($errors->any() && !$errors->has('otp'))
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <section class="auth-panel" data-auth-panel="login">
                <div class="auth-heading">
                    <h2>Login</h2>
                                    </div>

                <form method="POST" action="{{ route('login.attempt') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="auth_mode" value="login">

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Enter email"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input-wrap">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Enter password"
                                autocomplete="current-password"
                                required
                                data-password-input
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-password-toggle
                                aria-controls="password"
                                aria-label="Show password"
                            >
                                SHOW
                            </button>
                        </div>
                    </div>

                    <label class="remember">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            @checked(old('remember'))
                        >
                        Remember me
                    </label>

                    <button type="submit" class="login-button">LOGIN</button>

                    <button
                        type="button"
                        class="register-button"
                        data-auth-switch="register"
                    >
                        REGISTER
                    </button>
                </form>
            </section>

            <section class="auth-panel" data-auth-panel="register" hidden>
                <div class="auth-heading">
                    <span>CREATE W68 ACCOUNT</span>
                    <h2>Register</h2>
                    <p>Fill in the details below. Your account will only be created after email OTP verification.</p>
                </div>

                <form method="POST" action="{{ route('register.attempt') }}" class="auth-form">
                    @csrf
                    <input type="hidden" name="auth_mode" value="register">

                    <div class="register-grid">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input
                                id="username"
                                type="text"
                                name="username"
                                value="{{ old('username') }}"
                                placeholder="Enter username"
                                autocomplete="username"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="register_email">Email</label>
                            <input
                                id="register_email"
                                type="email"
                                name="register_email"
                                value="{{ old('register_email') }}"
                                placeholder="Enter email"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="register_password">Password</label>
                            <div class="password-input-wrap">
                                <input
                                    id="register_password"
                                    type="password"
                                    name="register_password"
                                    placeholder="Minimum 8 characters"
                                    autocomplete="new-password"
                                    required
                                    data-password-input
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-password-toggle
                                    aria-controls="register_password"
                                    aria-label="Show password"
                                >
                                    SHOW
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="register_password_confirmation">Retype Password</label>
                            <div class="password-input-wrap">
                                <input
                                    id="register_password_confirmation"
                                    type="password"
                                    name="register_password_confirmation"
                                    placeholder="Retype password"
                                    autocomplete="new-password"
                                    required
                                    data-password-input
                                >
                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-password-toggle
                                    aria-controls="register_password_confirmation"
                                    aria-label="Show password"
                                >
                                    SHOW
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="login-button">
                        REGISTER &amp; SEND OTP
                    </button>

                    <div class="auth-switch">
                        <span>Already registered?</span>
                        <button type="button" data-auth-switch="login">BACK TO LOGIN</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div
        class="otp-modal{{ $showOtpModal ? ' is-open' : '' }}"
        data-otp-modal
        data-otp-open="{{ $showOtpModal ? 'true' : 'false' }}"
        @if (!$showOtpModal) hidden @endif
        aria-hidden="{{ $showOtpModal ? 'false' : 'true' }}"
    >
        <div class="otp-backdrop"></div>

        <section class="otp-card" role="dialog" aria-modal="true" aria-labelledby="otp-title">
            <img
                class="otp-logo"
                src="{{ asset('build/assets/images/sidebar_logo.png') }}"
                alt="W68 Autoparts"
            >

            <span class="otp-kicker">
                {{ $visibleOtpPurpose === 'register' ? 'REGISTER VERIFICATION' : 'LOGIN VERIFICATION' }}
            </span>

            <h2 id="otp-title">Enter OTP</h2>

            <p>
                Enter the 6-digit code sent to
                <strong>{{ $visibleOtpEmail }}</strong>.
            </p>

            @if ($errors->has('otp'))
                <div class="otp-error">{{ $errors->first('otp') }}</div>
            @endif

            <form method="POST" action="{{ route('otp.verify') }}" class="otp-form">
                @csrf

                <label for="otp">6-Digit OTP</label>
                <input
                    id="otp"
                    type="text"
                    name="otp"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="000000"
                    required
                    data-otp-input
                >

                <button type="submit" class="otp-verify-button">VERIFY OTP</button>
            </form>

            <form method="POST" action="{{ route('otp.resend') }}" class="otp-resend-form">
                @csrf
                <button type="submit" class="otp-resend-button">RESEND OTP</button>
            </form>

            <small>OTP expires in 10 minutes. Maximum 5 incorrect attempts.</small>
        </section>
    </div>
</body>
</html>
