<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PontajMessage extends Notification
{
    public function __construct(public string $subject, public string $message, public string $url, public string $label = 'Deschide iPontaj') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->subject)->greeting('Bună!')->line($this->message)->action($this->label, $this->url);
    }
}
