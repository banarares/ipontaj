<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetAccountPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Resetează parola contului iPontaj')->greeting('Bună!')
            ->line('Am primit o cerere de resetare a parolei contului tău iPontaj.')
            ->action('Resetează parola', $this->resetUrl($notifiable))
            ->line('Linkul expiră în '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' de minute.')
            ->line('Dacă nu ai cerut resetarea, poți ignora acest mesaj.')->salutation('Echipa iPontaj');
    }
}
