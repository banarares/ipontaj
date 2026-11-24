<?php

namespace App\Notifications;

use App\Models\LegalAcceptance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LegalContractConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $acceptanceId)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptance = LegalAcceptance::findOrFail($this->acceptanceId);
        $text = 'iPontaj — condițiile abonamentului acceptate la '.$acceptance->accepted_at->toIso8601String()."\n\n".json_encode($acceptance->facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($acceptance->documents as $slug => $document) {
            $text .= "\n\n".$slug.' · '.$document['version'].' · SHA-256 '.$document['sha256']."\n\n".$document['text'];
        }

        return (new MailMessage)->subject('iPontaj — confirmarea abonamentului'.(($acceptance->facts['test_mode'] ?? true) ? ' de test' : ''))
            ->greeting('Condițiile abonamentului tău')
            ->line('Furnizor: BANA RARES-NICOLAE PFA, CIF 33388821, ONRC F2014000987292, Str. Națiunii nr. 65, Ploiești, Prahova. Contact: office@ipontaj.ro, 0735863399.')
            ->line('Plan Echipă: 49 lei/lună, preț final, până la 15 persoane active. Operator neînregistrat în scopuri de TVA. Reînnoire lunară până la anulare, fără perioadă minimă anuală.')
            ->line('În atașament găsești condițiile exacte și informarea privind retragerea acceptate. Pentru consumatori, cererea de începere în cele 14 zile de retragere este consemnată în dovadă, fără renunțare generală la drepturi.')
            ->line('Anularea reînnoirii se face din portalul de abonament sau prin contactarea operatorului. Retragere: https://ipontaj.ro/retragere.')
            ->line(($acceptance->facts['test_mode'] ?? true) ? 'Acesta este un abonament Stripe de TEST. Nu s-a efectuat o încasare reală și acest mesaj nu este o factură fiscală.' : 'Acest mesaj confirmă condițiile contractuale și nu înlocuiește factura fiscală.')
            ->attachData($text, 'ipontaj-conditii-abonament.txt', ['mime' => 'text/plain; charset=utf-8'])
            ->salutation('Echipa iPontaj');
    }
}
