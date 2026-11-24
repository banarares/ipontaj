<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $messageId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = ContactMessage::findOrFail($this->messageId);
        $data = $message->data;

        return (new MailMessage)->subject('iPontaj — mesaj de contact')->replyTo($data['email'], $data['first_name'].' '.$data['last_name'])
            ->view('mail.contact', ['reference' => $message->id, 'data' => $data]);
    }
}
