<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class W68OtpService
{
    public const EXPIRES_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_WAIT_SECONDS = 60;

    public function send(string $email, string $purpose): void
    {
        $this->assertMailConfigured();

        $purpose = $this->normalizePurpose($purpose);
        $email = mb_strtolower(trim($email));

        $latest = DB::connection('system')
            ->table('w68_auth_otps')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->first();

        if ($latest && $latest->created_at) {
            $createdAt = CarbonImmutable::parse($latest->created_at);
            $seconds = $createdAt->diffInSeconds(now());

            if ($seconds < self::RESEND_WAIT_SECONDS) {
                $wait = self::RESEND_WAIT_SECONDS - $seconds;

                throw new RuntimeException(
                    "Please wait {$wait} second" . ($wait === 1 ? '' : 's') . ' before requesting another OTP.'
                );
            }
        }

        $code = (string) random_int(100000, 999999);
        $now = now();

        DB::connection('system')
            ->table('w68_auth_otps')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->delete();

        DB::connection('system')->table('w68_auth_otps')->insert([
            'email' => $email,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(self::EXPIRES_MINUTES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $action = $purpose === 'register' ? 'registration' : 'login';

        Mail::raw(
            "Your W68 Autoparts {$action} OTP is: {$code}\n\n"
            . 'This code expires in ' . self::EXPIRES_MINUTES . " minutes.\n"
            . "Do not share this OTP with anyone.",
            function ($message) use ($email, $action): void {
                $message
                    ->to($email)
                    ->subject('W68 Autoparts ' . ucfirst($action) . ' OTP');
            }
        );
    }

    public function verify(string $email, string $purpose, string $code): bool
    {
        $purpose = $this->normalizePurpose($purpose);
        $email = mb_strtolower(trim($email));
        $code = trim($code);

        $record = DB::connection('system')
            ->table('w68_auth_otps')
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->first();

        if (!$record) {
            throw new RuntimeException('No active OTP was found. Please request a new OTP.');
        }

        if (CarbonImmutable::parse($record->expires_at)->isPast()) {
            $this->clear($email, $purpose);
            throw new RuntimeException('That OTP has expired. Please request a new OTP.');
        }

        if ((int) $record->attempts >= self::MAX_ATTEMPTS) {
            $this->clear($email, $purpose);
            throw new RuntimeException('Too many incorrect OTP attempts. Please request a new OTP.');
        }

        if (!Hash::check($code, $record->otp_hash)) {
            DB::connection('system')
                ->table('w68_auth_otps')
                ->where('id', $record->id)
                ->increment('attempts');

            $remaining = max(0, self::MAX_ATTEMPTS - ((int) $record->attempts + 1));

            throw new RuntimeException(
                'Incorrect OTP. '
                . ($remaining > 0 ? "{$remaining} attempt" . ($remaining === 1 ? '' : 's') . ' remaining.' : 'Request a new OTP.')
            );
        }

        $this->clear($email, $purpose);

        return true;
    }

    public function clear(string $email, string $purpose): void
    {
        DB::connection('system')
            ->table('w68_auth_otps')
            ->where('email', mb_strtolower(trim($email)))
            ->where('purpose', $this->normalizePurpose($purpose))
            ->delete();
    }

    private function normalizePurpose(string $purpose): string
    {
        if (!in_array($purpose, ['register', 'login'], true)) {
            throw new RuntimeException('Invalid OTP purpose.');
        }

        return $purpose;
    }

    private function assertMailConfigured(): void
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            throw new RuntimeException(
                'OTP email delivery is not configured yet. Configure SMTP in .env first.'
            );
        }

        $from = (string) config('mail.from.address');

        if ($from === '' || $from === 'hello@example.com') {
            throw new RuntimeException(
                'MAIL_FROM_ADDRESS is not configured yet. Configure SMTP in .env first.'
            );
        }
    }
}
