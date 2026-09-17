<?php

namespace App\Http\Controllers;

use App\Models\LoginAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CustomerSettingsController extends Controller
{
    private const OTP_EXPIRES_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 5;

    public function index(Request $request): View|RedirectResponse
    {
        $account = $this->accountTypeFive($request);

        if ($account instanceof RedirectResponse) {
            return $account;
        }

        $brands = DB::connection('masterlist')
            ->table('products')
            ->where('is_selected_for_report', 1)
            ->whereNotNull('category')
            ->whereRaw("TRIM(category) <> ''")
            ->selectRaw('TRIM(category) as brand')
            ->distinct()
            ->orderBy('brand')
            ->get()
            ->map(fn ($row) => [
                'brand' => (string) $row->brand,

                /*
                 * The uploaded core4_masterlist/core4_system_proposal schemas
                 * do not contain an account/brand discount mapping.
                 * Do not invent a percentage.
                 */
                'discount' => null,
            ])
            ->values();

        return view('settings', [
            'account' => $account,
            'brands' => $brands,
            'openPasswordOtp' => (bool) session('password_otp_required', false),
            'openPasswordModal' => (bool) session('open_password_modal', false),
        ]);
    }

    public function updateProfilePicture(Request $request): RedirectResponse
    {
        $account = $this->requireAccountTypeFive();

        $validated = $request->validate([
            'profile_picture' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif',
                'max:5120',
            ],
        ], [
            'profile_picture.mimetypes' => 'Please select a JPG, PNG, WEBP, or GIF image.',
            'profile_picture.max' => 'Profile picture must not exceed 5 MB.',
        ]);

        $file = $validated['profile_picture'];

        $account->profile_picture = file_get_contents($file->getRealPath());
        $account->profile_picture_mime = (string) ($file->getMimeType() ?: 'image/jpeg');
        $account->save();

        return redirect()
            ->route('settings')
            ->with('status', 'Profile picture updated successfully.');
    }

    public function requestPasswordChange(Request $request): RedirectResponse
    {
        $account = $this->requireAccountTypeFive();

        $validated = $request->validate([
            'new_password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ], [
            'new_password.confirmed' => 'The new password and confirmation do not match.',
        ]);

        $email = trim((string) ($account->Email ?? ''));

        if ($email === '') {
            return redirect()
                ->route('settings')
                ->with('open_password_modal', true)
                ->withErrors([
                    'new_password' => 'This account does not have an email address for OTP verification.',
                ]);
        }

        $code = random_int(100000, 999999);

        $request->session()->put([
            'w68_settings_pending_password_hash' => Hash::make($validated['new_password']),
            'w68_settings_password_otp_expires_at' => now()
                ->addMinutes(self::OTP_EXPIRES_MINUTES)
                ->timestamp,
            'w68_settings_password_otp_attempts' => 0,
        ]);

        $account->OTP_CODE = $code;
        $account->save();

        try {
            $this->sendPasswordOtp($account, $code);
        } catch (Throwable $exception) {
            report($exception);

            $account->OTP_CODE = null;
            $account->save();

            $this->clearPasswordChangeSession($request);

            return redirect()
                ->route('settings')
                ->with('open_password_modal', true)
                ->withErrors([
                    'new_password' => 'Password OTP could not be sent. Check storage/logs/laravel.log for the exact mail error.',
                ]);
        }

        return redirect()
            ->route('settings')
            ->with('password_otp_required', true)
            ->with('status', 'A 6-digit password-change OTP was sent to ' . $email . '.');
    }

    public function confirmPasswordChange(Request $request): RedirectResponse
    {
        $account = $this->requireAccountTypeFive();

        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $passwordHash = (string) $request->session()->get(
            'w68_settings_pending_password_hash',
            ''
        );

        $expiresAt = (int) $request->session()->get(
            'w68_settings_password_otp_expires_at',
            0
        );

        $attempts = (int) $request->session()->get(
            'w68_settings_password_otp_attempts',
            0
        );

        if ($passwordHash === '' || $expiresAt === 0 || !$account->OTP_CODE) {
            return redirect()
                ->route('settings')
                ->with('password_otp_required', true)
                ->withErrors([
                    'otp' => 'No active password-change OTP was found. Please request a new OTP.',
                ]);
        }

        if (time() > $expiresAt) {
            $account->OTP_CODE = null;
            $account->save();
            $this->clearPasswordChangeSession($request);

            return redirect()
                ->route('settings')
                ->with('open_password_modal', true)
                ->withErrors([
                    'new_password' => 'The password-change OTP expired. Please request a new OTP.',
                ]);
        }

        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            $account->OTP_CODE = null;
            $account->save();
            $this->clearPasswordChangeSession($request);

            return redirect()
                ->route('settings')
                ->with('open_password_modal', true)
                ->withErrors([
                    'new_password' => 'Too many incorrect OTP attempts. Please request a new OTP.',
                ]);
        }

        if (!hash_equals((string) $account->OTP_CODE, trim($validated['otp']))) {
            $attempts++;

            $request->session()->put(
                'w68_settings_password_otp_attempts',
                $attempts
            );

            $remaining = max(0, self::OTP_MAX_ATTEMPTS - $attempts);

            return redirect()
                ->route('settings')
                ->with('password_otp_required', true)
                ->withErrors([
                    'otp' => 'Incorrect OTP. '
                        . ($remaining > 0
                            ? $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.'
                            : 'Please request a new OTP.'),
                ]);
        }

        $account->Password = $passwordHash;
        $account->OTP_CODE = null;
        $account->save();

        $this->clearPasswordChangeSession($request);

        return redirect()
            ->route('settings')
            ->with('status', 'Password changed successfully.');
    }

    private function sendPasswordOtp(LoginAccount $account, int $code): void
    {
        if (!$account->Email) {
            throw new RuntimeException('This account does not have an email address.');
        }

        $html = $this->passwordOtpEmailHtml($code);

        Mail::html(
            $html,
            function ($message) use ($account): void {
                $message
                    ->from(
                        (string) config('mail.from.address'),
                        'W68 Autoparts & Service Center'
                    )
                    ->to($account->Email)
                    ->subject('W68 Autoparts & Service Center - Password Change OTP');
            }
        );
    }

    private function passwordOtpEmailHtml(int $code): string
    {
        $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
        $minutes = self::OTP_EXPIRES_MINUTES;

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>W68 Password Change OTP</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f4;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="width:100%;background:#f3f5f4;">
<tr>
<td align="center" style="padding:32px 14px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="width:100%;max-width:580px;background:#5f0b1c;border:4px solid #ffd633;border-radius:16px;">
        <tr>
            <td align="center" style="padding:34px 28px 12px;">
                <div style="font-size:24px;line-height:1.25;font-weight:900;color:#ffd633;">
                    W68 Autoparts &amp; Service Center
                </div>
                <div style="margin-top:7px;font-size:13px;font-weight:700;color:#ffffff;">
                    Password Change Verification
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:14px 28px;color:#ffffff;font-size:13px;line-height:1.6;">
                Enter this 6-digit OTP in your W68 Settings page to confirm your new password.
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:18px 28px;">
                <div style="display:inline-block;min-width:260px;padding:20px 24px;border:2px solid #ffd633;border-radius:12px;background:#4a0816;color:#ffd633;font-size:40px;line-height:1;font-weight:900;letter-spacing:10px;">
                    {$safeCode}
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:8px 28px 32px;color:#ffffff;font-size:12px;line-height:1.6;">
                This OTP expires in <strong style="color:#ffd633;">{$minutes} minutes</strong>.<br>
                <span style="color:#f6dfe4;">Do not share this OTP with anyone.</span>
            </td>
        </tr>
    </table>
</td>
</tr>
</table>
</body>
</html>
HTML;
    }

    private function accountTypeFive(Request $request): LoginAccount|RedirectResponse
    {
        $account = Auth::user();

        if (!$account || (int) ($account->account_type ?? 0) !== 5) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'W68 Settings requires an account_type = 5 account.',
                ]);
        }

        return $account;
    }

    private function requireAccountTypeFive(): LoginAccount
    {
        $account = Auth::user();

        abort_unless(
            $account instanceof LoginAccount
                && (int) ($account->account_type ?? 0) === 5,
            403,
            'W68 Settings requires account_type = 5.'
        );

        return $account;
    }

    private function clearPasswordChangeSession(Request $request): void
    {
        $request->session()->forget([
            'w68_settings_pending_password_hash',
            'w68_settings_password_otp_expires_at',
            'w68_settings_password_otp_attempts',
        ]);
    }
}
