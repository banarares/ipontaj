<?php

namespace App\Console\Commands;

use App\Support\EnvironmentFile;
use Illuminate\Console\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ListenStripeTestCommand extends Command
{
    protected $signature = 'pontaj:stripe:listen {--forward-to=http://127.0.0.1:8000/stripe/webhook}';

    protected $description = 'Pornește ascultarea webhookurilor Stripe de test și salvează secretul local fără afișare';

    public function handle(): int
    {
        $secret = config('cashier.secret');
        $destination = $this->option('forward-to');
        if (! is_string($secret) || (! str_starts_with($secret, 'sk_test_') && ! str_starts_with($secret, 'rk_test_'))) {
            $this->error('Sunt acceptate numai chei Stripe de test.');

            return self::FAILURE;
        }
        if (! is_string($destination) || ! in_array(parse_url($destination, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true)) {
            $this->error('Destinația trebuie să fie aplicația locală.');

            return self::FAILURE;
        }
        $binary = is_executable(base_path('.runtime/stripe/stripe')) ? base_path('.runtime/stripe/stripe') : (new ExecutableFinder)->find('stripe');
        if (! $binary) {
            $this->error('Instalează Stripe CLI în .runtime/stripe/stripe.');

            return self::FAILURE;
        }
        $process = new Process([$binary, 'listen', '--skip-update', '--events', 'customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,customer.updated,customer.deleted,payment_method.automatically_updated,invoice.payment_action_required,invoice.payment_succeeded,checkout.session.completed', '--forward-to', $destination], base_path(), ['STRIPE_API_KEY' => $secret]);
        $process->setTimeout(null);
        $buffer = '';
        $configured = false;
        $process->run(function (string $type, string $output) use (&$buffer, &$configured, $secret): void {
            $buffer .= $output;
            while (($end = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $end + 1);
                $buffer = substr($buffer, $end + 1);
                if (! $configured && preg_match('/whsec_[A-Za-z0-9]+/', $line, $match)) {
                    EnvironmentFile::update(['STRIPE_WEBHOOK_SECRET' => $match[0]]);
                    $this->callSilently('config:clear');
                    $configured = true;
                    $this->info('Secretul webhook a fost salvat în .env. Listenerul de test este activ.');
                }
                $safe = str_replace($secret, '[redactat]', $line);
                $safe = preg_replace('/(?:whsec_|(?:sk|rk|pk)_(?:test|live)_)[A-Za-z0-9]+/', '[secret redactat]', $safe);
                $this->output->write($safe);
            }
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
