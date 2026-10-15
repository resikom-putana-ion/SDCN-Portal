<?php

namespace App\Services;

use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetMail
{
    public const UNAVAILABLE = 'mail.unavailable';

    public function configured(): bool
    {
        return config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host'))
            && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password'))
            && (bool) filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL);
    }

    public function send(string $email): string
    {
        // Log/array mailers must not show a successful delivery on the public form.
        if (! $this->configured()) {
            return self::UNAVAILABLE;
        }

        return Password::sendResetLink(['email' => $email], function ($user, $token) {
            try {
                $user->sendPasswordResetNotification($token);
            } catch (TransportExceptionInterface $exception) {
                Password::deleteToken($user);
                // SMTP diagnostics may contain credentials, addresses or reset URLs.
                Log::warning('Password recovery email transport failed.', ['type' => $exception::class]);

                return self::UNAVAILABLE;
            }

            event(new PasswordResetLinkSent($user));

            return Password::RESET_LINK_SENT;
        });
    }
}
