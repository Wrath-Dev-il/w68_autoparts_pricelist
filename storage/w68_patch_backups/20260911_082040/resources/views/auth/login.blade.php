<!DOCTYPE html>
<html lang="en" data-auth-mode="{{ session('auth_mode', old('auth_mode', 'login')) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>W68 Autoparts Login</title>
    <link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20260911-v24">
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
    <script src="{{ asset('js/w68-auth.js') }}?v=20260911-v24" defer></script>
</head>
<body>
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
                <div class="status-message">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any() && !$errors->has('otp'))
                <div class="error-message">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="auth-panel" data-auth-panel="login">
                <div class="auth-heading">
                    <span>ACCOUNT ACCESS</span>
                    <h2>Login</h2>
                    <p>Your password is checked first, then a 6-digit OTP is sent to your email.</p>
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
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required
                        >
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

                    <button type="submit" class="login-button">
                        LOGIN
                    </button>

                    <div class="auth-switch">
                        <span>Don't have a W68 account?</span>
                        <button type="button" data-auth-switch="register">REGISTER</button>
                    </div>
                </form>
            </section>

            <section class="auth-panel" data-auth-panel="register" hidden>
                <div class="auth-heading">
                    <span>CREATE ACCOUNT</span>
                    <h2>Register</h2>
                    <p>Complete the form below. We will send an OTP to verify your email before creating the account.</p>
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
                            <input
                                id="register_password"
                                type="password"
                                name="register_password"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="register_password_confirmation">Retype Password</label>
                            <input
                                id="register_password_confirmation"
                                type="password"
                                name="register_password_confirmation"
                                placeholder="Retype password"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit" class="login-button register-submit">
                        REGISTER &amp; SEND OTP
                    </button>

                    <div class="auth-switch">
                        <span>Already have an account?</span>
                        <button type="button" data-auth-switch="login">BACK TO LOGIN</button>
                    </div>
                </form>
            </section>
        </div>
    </div>

    <div
        class="otp-modal"
        data-otp-modal
        @if (!session('otp_required') && !$errors->has('otp')) hidden @endif
        aria-hidden="{{ session('otp_required') || $errors->has('otp') ? 'false' : 'true' }}"
    >
        <div class="otp-backdrop"></div>

        <section class="otp-card" role="dialog" aria-modal="true" aria-labelledby="otp-title">
            <img
                class="otp-logo"
                src="{{ asset('build/assets/images/sidebar_logo.png') }}"
                alt="W68 Autoparts"
            >

            <span class="otp-kicker">
                {{ session('otp_purpose') === 'register' ? 'REGISTER VERIFICATION' : 'LOGIN VERIFICATION' }}
            </span>

            <h2 id="otp-title">Enter your OTP</h2>

            <p>
                Enter the 6-digit code sent to
                <strong>{{ session('otp_email', session('w68_otp_email')) }}</strong>.
            </p>

            @if ($errors->has('otp'))
                <div class="otp-error">
                    {{ $errors->first('otp') }}
                </div>
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

                <button type="submit" class="otp-verify-button">
                    VERIFY OTP
                </button>
            </form>

            <form method="POST" action="{{ route('otp.resend') }}" class="otp-resend-form">
                @csrf
                <button type="submit" class="otp-resend-button">
                    RESEND OTP
                </button>
            </form>

            <small>OTP expires in 10 minutes and can only be tried 5 times.</small>
        </section>
    </div>
</body>
</html>
