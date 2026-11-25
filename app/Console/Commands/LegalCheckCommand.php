<?php

namespace App\Console\Commands;

use App\Services\LegalReadiness;
use Illuminate\Console\Command;

class LegalCheckCommand extends Command
{
    protected $signature = 'pontaj:legal:check';

    protected $description = 'Listează demersurile de lansare încă neconfirmate; nu reprezintă o certificare juridică';

    public function handle(LegalReadiness $readiness): int
    {
        $blockers = $readiness->blockers();
        if ($blockers === []) {
            $this->info('Verificările de lansare sunt marcate ca îndeplinite de operator. Verifică separat infrastructura și documentele semnate.');

            return self::SUCCESS;
        }
        $this->warn('Demersuri declarate încă neconfirmate de operator (raport separat de disponibilitatea tehnică a plății):');
        foreach ($blockers as $blocker) {
            $this->line('• '.$blocker);
        }

        return self::FAILURE;
    }
}
