<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>W68 Autoparts Login</title>
    <link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20260911-v19">
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <img
                class="login-logo"
                src="{{ asset('build/assets/images/sidebar_logo.png') }}"
                alt="W68 Autoparts Logo"
            >

            <h1>W68 AUTOPARTS</h1>
            <p class="subtitle">Online Pricelist</p>

            @if ($errors->any())
                <div class="error-message">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Enter email"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Enter password"
                        required
                    >
                </div>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    Remember me
                </label>

                <button type="submit" class="login-button">
                    LOGIN
                </button>
            </form>

        </div>
    </div>
</body>
</html>
