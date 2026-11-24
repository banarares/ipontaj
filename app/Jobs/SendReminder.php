<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Membership;
use App\Notifications\PontajMessage;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $companyId, public int $memberId, public string $date, public bool $digest = false) {}

    public function handle(AttendanceService $attendance): void
    {
        DB::transaction(function () use ($attendance) {
            $company = Company::find($this->companyId);
            $member = Membership::with('user')->where('company_id', $this->companyId)->find($this->memberId);
            if (! $company || ! $member || ! $member->user_id || $company->is_demo || ! $company->reminders || ! $member->active || ! $member->reminders) {
                return;
            }
            $key = ($this->digest ? 'digest' : 'day').":{$company->id}:{$member->id}:{$this->date}";
            DB::table('notification_deliveries')->insertOrIgnore(['company_id' => $company->id, 'key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            $delivery = DB::table('notification_deliveries')->where('key', $key)->lockForUpdate()->first();
            if ($delivery->sent_at) {
                return;
            }
            if ($this->digest) {
                if ($member->role !== 'owner') {
                    return;
                }$issues = $attendance->summary($company, substr($this->date, 0, 7))['issues'];
                if (! $issues) {
                    return;
                }
                $message = new PontajMessage('Sinteza echipei · '.$company->name, count($issues).' înregistrări necesită atenție. Verifică zilele lipsă și absențele.', url('/month?month='.substr($this->date, 0, 7)));
            } else {
                if (! $member->tracked || ! $attendance->planned($member, $this->date) || $member->entries()->whereDate('date', $this->date)->exists()) {
                    return;
                }
                $message = new PontajMessage('Confirmă ziua în iPontaj', 'Ziua de '.CarbonImmutable::parse($this->date)->format('d.m.Y').' este încă neconfirmată.', url('/today?date='.$this->date));
            }
            $member->user->notify($message);
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['sent_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
