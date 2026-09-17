<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reset Password | W68 Autoparts</title>

    <link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20260917-v105">
    <script src="{{ asset('js/w68-auth.js') }}?v=20260917-v105" defer></script>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="auth-brand">
                <img
                    class="login-logo"
                    src="{{ asset('images/sidebar_logo.png') }}"
                    alt="W68 Autoparts Logo"
                >

                <div>
                    <h1>W68 AUTOPARTS</h1>
                    <p class="subtitle">Password Reset</p>
                </div>
            </div>

            @if (session('status'))
                <div class="status-message">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-message">{{ $errors->first() }}</div>
            @endif

            <section class="auth-panel">
                <div class="auth-heading">
                    <span>SECURE PASSWORD RESET</span>
                    <h2>Set New Password</h2>
                    <p>
                        OTP verified for <strong>{{ $resetEmail }}</strong>.
                        Enter your new password twice to continue.
                    </p>
                </div>

                <form method="POST" action="{{ route('password.reset.update') }}" class="auth-form">
                    @csrf

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="password-input-wrap">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
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

                    <div class="form-group">
                        <label for="password_confirmation">Retype Password</label>
                        <div class="password-input-wrap">
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                placeholder="Retype new password"
                                autocomplete="new-password"
                                required
                                data-password-input
                            >
                            <button
                                type="button"
                                class="password-toggle"
                                data-password-toggle
                                aria-controls="password_confirmation"
                                aria-label="Show password"
                            >
                                SHOW
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-button">RESET AND LOGIN</button>

                    <div class="auth-switch">
                        <a href="{{ route('login') }}" style="font-weight:800;color:#800020;text-decoration:none;">
                            BACK TO LOGIN
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
