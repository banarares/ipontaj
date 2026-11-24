<?php

namespace App\Services;

class LegalReadiness
{
    /** @return list<string> */
    public function blockers(): array
    {
        $missing = [];
        foreach (['hosting_dpa' => 'Acordul GDPR și subîmputerniciții cyber_Folks', 'stripe_dpa' => 'Acordul și transferurile Stripe', 'caen' => 'Activitățile autorizate și CAEN aplicabil', 'invoicing' => 'Facturarea fiscală și RO e-Factura, verificate cu contabilul', 'operations' => 'Proceduri GDPR, ștergere, incidente și restaurare verificate', 'mail' => 'SMTP și livrarea confirmărilor, SPF/DKIM/DMARC', 'consumer_flow' => 'Checkout și confirmare contractuală pe suport durabil, verificate integral'] as $key => $label) {
            if (! config('legal.checks.'.$key)) {
                $missing[] = $label;
            }
        }
        foreach (['contract_entity' => 'Entitatea contractuală a furnizorului reseller', 'data_location' => 'Locațiile datelor, emailului și copiilor de siguranță'] as $key => $label) {
            if (! config('legal.hosting.'.$key)) {
                $missing[] = $label;
            }
        }
        if (config('legal.hosting.backup_retention_days') < 1) {
            $missing[] = 'Retenția backupurilor confirmată pentru cont';
        }
        if (! config('legal.retention.plan_approved') || config('legal.retention.log_days') < 1) {
            $missing[] = 'Plan de retenție aprobat și aplicat, inclusiv logurile';
        }
        if (! is_file(config('legal.source_archive'))) {
            $missing[] = 'Arhiva sursei corespunzătoare versiunii publicate';
        }
        if (! is_file(public_path(config('legal.anpc_sal_asset')))) {
            $missing[] = 'Pictograma SAL oficială actuală';
        }
        if (! config('legal.publication_approved')) {
            $missing[] = 'Documentele și condițiile de lansare verificate de operator';
        }

        return $missing;
    }
}
