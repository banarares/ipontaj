<?php

namespace App\Notifications;

use App\Models\LegalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class LegalRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $requestId, public bool $operator = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = LegalRequest::findOrFail($this->requestId);
        $url = URL::temporarySignedRoute('legal.request.receipt', now()->addDays(7), ['legalRequest' => $request->id]);

        return (new MailMessage)->subject('iPontaj — confirmare '.($request->kind === 'withdrawal' ? 'declarație de retragere' : 'solicitare privind datele'))
            ->greeting($this->operator ? 'Solicitare de procesat' : 'Am înregistrat solicitarea ta')
            ->line('Referință: '.$request->id)
            ->line('Primită la: '.$request->received_at->setTimezone('Europe/Bucharest')->format('d.m.Y H:i:s'))
            ->line('Nume: '.$request->name)
            ->line('Email pentru răspuns: '.$request->email)
            ->line('Contract: '.($request->contract_reference ?? 'Solicitare privind datele'))
            ->line($request->message ?? '')
            ->line($this->operator ? 'Verifică identitatea și situația contractului. Înregistrează soluția și respectă termenul legal. Rambursarea și anularea nu sunt automate.' : 'Aceasta este dovada primirii declarației, fără a anticipa soluția. Nu reprezintă o confirmare că o rambursare sau ștergere a fost efectuată.')
            ->action('Descarcă dovada primirii', $url)
            ->salutation('BANA RARES-NICOLAE PFA · office@ipontaj.ro');
    }
}
