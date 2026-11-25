<?php

use App\Jobs\SendReminder;
use App\Models\Company;
use App\Services\AttendanceService;
use App\Services\DemoWorkspaces;
use App\Support\RunScheduledCommand;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('pontaj:remind', function (AttendanceService $attendance) {
    Company::where('is_demo', false)->where('reminders', true)->chunkById(100, function ($companies) use ($attendance) {
        foreach ($companies as $company) {
            $now = now($company->timezone);
            foreach ($company->memberships()->whereNotNull('user_id')->where('active', true)->where('reminders', true)->get() as $member) {
                // Include yesterday to handle overnight shifts.
                foreach ([$now->toDateString(), $now->subDay()->toDateString()] as $date) {
                    $schedule = $attendance->schedule($member, $date);
                    if (! $schedule) {
                        continue;
                    }
                    $end = Carbon::parse($date.' '.$schedule->end_time, $company->timezone);
                    $start = Carbon::parse($date.' '.$schedule->start_time, $company->timezone);
                    if ($end <= $start) {
                        $end = $end->addDay();
                    }
                    if ($now >= $end->addMinutes(30) && $now < $end->addHours(6)) {
                        SendReminder::dispatch($company->id, $member->id, $date);
                    }
                }
                if ($member->role === 'owner' && $now->hour >= 19 && $now->hour < 21) {
                    SendReminder::dispatch($company->id, $member->id, $now->toDateString(), true);
                }
            }
        }
    });
    $this->info('Mementourile eligibile au fost trimise în coadă.');
})->purpose('Mementouri pentru zile neconfirmate, în fusul firmei');
Artisan::command('pontaj:clean-demo {--dry-run : Afișează numai numărul înregistrărilor eligibile}', function (DemoWorkspaces $demos) {
    $result = $demos->prune((bool) $this->option('dry-run'));
    $this->info(($this->option('dry-run') ? 'Previzualizare' : 'Șterse').': '.$result['companies'].' firme demo, '.$result['users'].' utilizatori demo.');
});
Schedule::call(new RunScheduledCommand('pontaj:remind'))->name('pontaj:remind')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(new RunScheduledCommand('pontaj:clean-demo'))->name('pontaj:clean-demo')->everyTenMinutes()->withoutOverlapping();
Schedule::call(new RunScheduledCommand('pontaj:logs:prune', ['--apply' => true]))->name('pontaj:logs:prune')->dailyAt('03:20')
    ->when(fn () => config('logging.default') === 'daily' && (int) config('legal.retention.log_days') > 0)
    ->withoutOverlapping();
