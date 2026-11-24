<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Command;

class ContactMessagesCommand extends Command
{
    protected $signature = 'pontaj:contact {--show= : Referința mesajului de citit} {--resolve= : Marchează mesajul ca soluționat; nu trimite email}';

    protected $description = 'Consultă mesajele de contact salvate și înregistrează soluționarea lor';

    public function handle(): int
    {
        $id = $this->option('show') ?: $this->option('resolve');
        if ($id) {
            $message = ContactMessage::find($id);
            if (! $message) {
                $this->error('Mesajul nu există.');

                return self::FAILURE;
            }
            if ($this->option('resolve')) {
                $message->update(['status' => 'resolved']);
                $this->info('Mesaj marcat ca soluționat. Nu s-a trimis un răspuns automat.');
            } else {
                $this->line(json_encode($message->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        }
        $this->table(['Referință', 'Stare', 'Primit'], ContactMessage::latest()->limit(50)->get()->map(fn ($message) => [$message->id, $message->status, $message->created_at->toIso8601String()])->all());

        return self::SUCCESS;
    }
}
