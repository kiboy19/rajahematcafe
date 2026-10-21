<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyCustomerEmail extends BaseVerifyEmail
{
    /**
     * Bawaan framework menunjuk route `verification.verify` (milik staf); pelanggan
     * memakai route sendiri agar kedua kanal tidak tertukar.
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'customer.verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.verify_subject'))
            ->line(__('auth.verify_line'))
            ->action(__('auth.verify_action'), $url)
            ->line(__('auth.verify_disclaimer'));
    }
}
