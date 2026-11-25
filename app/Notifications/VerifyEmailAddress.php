<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailAddress extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable instanceof MustVerifyEmail && ! $notifiable->hasVerifiedEmail();
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirmă adresa de email pentru iPontaj')
            ->greeting('Bună, '.$notifiable->first_name.'!')
            ->line('Bine ai venit în iPontaj! Confirmă adresa de email pentru a putea intra în cont și pregăti echipa.')
            ->action('Confirmă adresa de email', $this->verificationUrl($notifiable))
            ->line('Linkul este valabil 60 de minute. Dacă nu ai creat acest cont, poți ignora mesajul.')
            ->salutation('Echipa iPontaj');
    }
}
