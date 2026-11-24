<?php

namespace App\Console\Commands;

use App\Models\LegalRequest;
use Illuminate\Console\Command;

class LegalRequestsCommand extends Command
{
    protected $signature = 'pontaj:legal:requests {--show= : Referința solicitării pentru detalii} {--resolve= : Referința solicitării soluționate} {--resolution= : Soluția comunicată solicitantului, fără secrete sau date excesive}';

    protected $description = 'Registrul intern al retragerilor și cererilor GDPR; nu trimite email și nu rambursează plăți';

    public function handle(): int
    {
        if ($id = $this->option('resolve')) {
            $request = LegalRequest::find($id);
            $resolution = $this->option('resolution');
            if (! $request || ! is_string($resolution) || trim($resolution) === '' || mb_strlen($resolution) > 2000) {
                $this->error('Indică o referință validă și soluția deja comunicată, de cel mult 2000 de caractere.');

                return self::FAILURE;
            }
            $request->update(['status' => 'resolved', 'resolved_at' => now(), 'resolution' => $resolution]);
            $this->info('Soluția a fost înregistrată. Comanda nu a trimis email și nu a efectuat plăți.');

            return self::SUCCESS;
        }
        if ($id = $this->option('show')) {
            $request = LegalRequest::find($id);
            if (! $request) {
                $this->error('Referință inexistentă.');

                return self::FAILURE;
            }
            $this->line(json_encode($request->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        $this->table(['Referință', 'Tip', 'Primită', 'Termen de urmărit', 'Stare'], LegalRequest::where('status', 'pending')->orderBy('due_at')->get()->map(fn ($request) => [$request->id, $request->kind, $request->received_at->format('Y-m-d H:i'), $request->due_at->format('Y-m-d H:i'), $request->due_at->isPast() ? 'DE VERIFICAT URGENT' : 'În așteptare'])->all());

        return self::SUCCESS;
    }
}
