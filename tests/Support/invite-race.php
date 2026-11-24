<?php

use App\Models\Company;
use App\Services\TeamService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['ipontaj.billing_enabled' => true]);
while (microtime(true) < (float) $argv[3]) {
    usleep(1000);
}
try {
    app(TeamService::class)->invite(Company::findOrFail($argv[1]), ['first_name' => 'Concurent', 'last_name' => 'fictiv', 'email' => $argv[2]]);
    echo 'accepted';
} catch (ValidationException) {
    echo 'limited';
}
