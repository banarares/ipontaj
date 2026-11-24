<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $invitationId, public string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $i = Invitation::with('company')->find($this->invitationId);

        return $i && ! $i->company->is_demo && ! $i->revoked_at && ! $i->accepted_at && $i->expires_at->isFuture() && hash_equals($i->token_hash, hash('sha256', basename($this->url)));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = Invitation::with('company')->findOrFail($this->invitationId);

        return (new MailMessage)->subject('Invitație în echipa '.$i->company->name)->greeting('Bună, '.$i->name.'!')->line('Ai fost invitat în iPontaj. Linkul expiră în 7 zile.')->action('Vezi invitația', $this->url)->line('Creează un cont cu adresa la care ai primit invitația.');
    }
}
