<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="W68 Autoparts & Service Center customer settings.">
    <title>W68 Autoparts & Service Center | Settings</title>
    <link rel="icon" href="{{ asset('images/sidebar_logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/w68-settings.css') }}?v=20260915-v71">
    <script src="{{ asset('js/w68-settings.js') }}?v=20260915-v71" defer></script>
</head>
<body
    data-settings-version="20260915-v71"
    data-open-password-otp="{{ $openPasswordOtp ? '1' : '0' }}"
    data-open-password-modal="{{ $openPasswordModal ? '1' : '0' }}"
>
    @php
        $logo = asset('images/sidebar_logo.png');
        $pictureUrl = route('home.profile-picture') . '?v=' . (optional($account->updated_at)->timestamp ?? time());
    @endphp

    <header class="settings-header">
        <div class="settings-header-shell">
            <a class="settings-brand" href="{{ route('home') }}">
                <img src="{{ $logo }}" alt="W68 Autoparts & Service Center">
                <span>
                    <strong>W68 AUTOPARTS</strong>
                    <small>Service Center â€¢ Account Settings</small>
                </span>
            </a>

            <a class="settings-home-link" href="{{ route('home') }}">â† BACK TO HOME</a>
        </div>
    </header>

    <main class="settings-shell">
        <div class="settings-title">
            <span>ACCOUNT TYPE 5</span>
            <h1>Settings</h1>
            <p>Manage your profile, account security, and the brand discounts assigned to this logged-in customer.</p>
        </div>

        @if (session('status'))
            <div class="settings-status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="settings-errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="settings-profile-card">
            <button
                type="button"
                class="settings-avatar-button"
                data-profile-modal-open
                aria-label="Update profile picture"
            >
                <img
                    src="{{ $pictureUrl }}"
                    alt="{{ $account->User_ID }} profile picture"
                    data-current-profile-picture
                >
                <span class="settings-avatar-pencil" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="m4 16.5-.7 4.2 4.2-.7L19 8.5 15.5 5 4 16.5Z"/>
                        <path d="m13.8 6.7 3.5 3.5"/>
                    </svg>
                </span>
            </button>

            <div class="settings-account-info">
                <div class="settings-info-row">
                    <span>User Name</span>
                    <strong>{{ $account->User_ID ?: 'â€”' }}</strong>
                </div>

                <div class="settings-info-row">
                    <span>Email</span>
                    <strong>{{ $account->Email ?: 'â€”' }}</strong>
                </div>

                <div class="settings-info-row settings-password-row">
                    <span>Password</span>
                    <strong>â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢â€¢</strong>
                    <button type="button" data-password-modal-open>CHANGE PASS</button>
                </div>
            </div>
        </section>

        <section class="brand-list-section">
            <div class="brand-list-heading">
                <div>
                    <span>YOUR CATALOG</span>
                    <h2>Your Brand Discounts</h2>
                </div>
                <p>Discounts below come from the brand discounts assigned to this logged-in customer. Click a brand to view its products.</p>
            </div>

            <div class="brand-list-table-wrap">
                <table class="brand-list-table">
                    <thead>
                        <tr>
                            <th>Brand</th>
                            <th>Discount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($brands as $brand)
                            <tr>
                                <td>
                                    <a href="{{ route('home', ['brand' => $brand['brand']]) }}#products">
                                        <strong>{{ $brand['brand'] }}</strong>
                                        <small>VIEW PRODUCTS â†’</small>
                                    </a>
                                </td>
                                <td>
                                    {{ $brand['discount'] }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="brand-empty">No brand discounts are assigned to this customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="brand-discount-note">
                Customer ID: <strong>{{ $customerId ?: 'Not linked' }}</strong>
                &nbsp;&bull;&nbsp;
                Discounted brands shown: <strong>{{ $discountedBrandCount }}</strong>.
                Only brands with an assigned discount greater than 0% are displayed.
            </p>
        </section>
    </main>

    {{-- Profile picture modal --}}
    <div class="settings-modal" data-profile-modal hidden aria-hidden="true">
        <button type="button" class="settings-modal-backdrop" data-profile-modal-close aria-label="Close"></button>

        <section class="settings-modal-card profile-picture-modal-card" role="dialog" aria-modal="true" aria-labelledby="profile-picture-modal-title">
            <button type="button" class="settings-modal-x" data-profile-modal-close aria-label="Close profile picture modal">Ã—</button>
            <header>
                <span>PROFILE PICTURE</span>
                <h2 id="profile-picture-modal-title">Update Picture</h2>
            </header>

            <form
                action="{{ route('settings.profile-picture.update') }}"
                method="POST"
                enctype="multipart/form-data"
                data-profile-form
            >
                @csrf

                <div class="profile-preview-wrap">
                    <img
                        src="{{ $pictureUrl }}"
                        alt="Profile picture preview"
                        data-profile-preview
                    >

                    <button
                        type="button"
                        class="profile-preview-pencil"
                        data-profile-file-trigger
                        aria-label="Choose profile picture"
                    >
                        <svg viewBox="0 0 24 24">
                            <path d="m4 16.5-.7 4.2 4.2-.7L19 8.5 15.5 5 4 16.5Z"/>
                            <path d="m13.8 6.7 3.5 3.5"/>
                        </svg>
                    </button>
                </div>

                <input
                    type="file"
                    name="profile_picture"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    data-profile-file
                    hidden
                    required
                >

                <p class="profile-file-name" data-profile-file-name>
                    Click the pencil icon to select JPG, PNG, WEBP, or GIF.
                </p>

                <div class="settings-modal-actions">
                    <button type="button" class="settings-secondary-button" data-profile-modal-close>CANCEL</button>
                    <button type="submit" class="settings-primary-button" data-profile-save disabled>SAVE PICTURE</button>
                </div>
            </form>
        </section>
    </div>

    {{-- Change password modal --}}
    <div class="settings-modal" data-password-modal hidden aria-hidden="true">
        <button type="button" class="settings-modal-backdrop" data-password-modal-close aria-label="Close"></button>

        <section class="settings-modal-card" role="dialog" aria-modal="true" aria-labelledby="password-modal-title">
            <button type="button" class="settings-modal-x" data-password-modal-close aria-label="Close change password modal">Ã—</button>
            <header>
                <span>SECURITY</span>
                <h2 id="password-modal-title">Change Password</h2>
                <p>Enter your new password. W68 will email an OTP to {{ $account->Email }} before changing it.</p>
            </header>

            <form action="{{ route('settings.password.request') }}" method="POST">
                @csrf

                <label class="settings-field">
                    <span>New Password</span>
                    <div class="settings-password-input">
                        <input
                            type="password"
                            name="new_password"
                            placeholder="Minimum 8 characters"
                            autocomplete="new-password"
                            required
                            data-password-input
                        >
                        <button type="button" data-password-toggle>SHOW</button>
                    </div>
                </label>

                <label class="settings-field">
                    <span>Retype New Password</span>
                    <div class="settings-password-input">
                        <input
                            type="password"
                            name="new_password_confirmation"
                            placeholder="Retype new password"
                            autocomplete="new-password"
                            required
                            data-password-input
                        >
                        <button type="button" data-password-toggle>SHOW</button>
                    </div>
                </label>

                <div class="settings-modal-actions">
                    <button type="button" class="settings-secondary-button" data-password-modal-close>CANCEL</button>
                    <button type="submit" class="settings-primary-button">SEND OTP</button>
                </div>
            </form>
        </section>
    </div>

    {{-- Password OTP modal --}}
    <div class="settings-modal" data-password-otp-modal hidden aria-hidden="true">
        <div class="settings-modal-backdrop"></div>

        <section class="settings-modal-card otp-settings-card" role="dialog" aria-modal="true">
            <header>
                <span>PASSWORD VERIFICATION</span>
                <h2>Enter OTP</h2>
                <p>A 6-digit OTP was sent to <strong>{{ $account->Email }}</strong>.</p>
            </header>

            <form action="{{ route('settings.password.confirm') }}" method="POST">
                @csrf

                <label class="settings-field">
                    <span>OTP Code</span>
                    <input
                        class="settings-otp-input"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        name="otp"
                        placeholder="000000"
                        required
                        data-settings-otp-input
                    >
                </label>

                <div class="settings-modal-actions">
                    <button type="button" class="settings-secondary-button" data-password-otp-back>BACK</button>
                    <button type="submit" class="settings-primary-button">CONFIRM</button>
                </div>
            </form>
        </section>
    </div>
</body>
</html>
