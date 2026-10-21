<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetCustomerPassword extends BaseResetPassword
{
    /**
     * Arahkan tautan ke halaman atur ulang milik pelanggan, bukan halaman staf.
     */
    protected function resetUrl($notifiable): string
    {
        return url(route('customer.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.reset_subject'))
            ->line(__('auth.reset_line_first'))
            ->action(__('auth.reset_action'), $url)
            ->line(__('auth.reset_expire', ['count' => config('auth.passwords.customers.expire')]))
            ->line(__('auth.reset_disclaimer'));
    }
}
