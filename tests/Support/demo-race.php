<?php

use App\Services\DemoWorkspaces;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['ipontaj.demo.max_workspaces' => 1, 'ipontaj.demo.session_minutes' => 60]);
while (microtime(true) < (float) $argv[1]) {
    usleep(1000);
}
echo app(DemoWorkspaces::class)->start() ? 'created' : 'limited';
