<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\MonthClosure;
use App\Notifications\PontajMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class SendAccountantExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $closureId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $closure = MonthClosure::find($this->closureId);
            if (! $closure?->approved_at) {
                return;
            }
            $company = Company::lockForUpdate()->find($closure->company_id);
            if (! $company || $company->is_demo || ! $company->subscribed('default') || ! $company->accountant_confirmed_at || ! $company->accountant_email) {
                return;
            }
            $key = 'accountant:'.$closure->id.':'.$closure->approval_version;
            DB::table('notification_deliveries')->insertOrIgnore(['company_id' => $company->id, 'key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            $delivery = DB::table('notification_deliveries')->where('key', $key)->lockForUpdate()->first();
            if ($delivery->sent_at) {
                return;
            }
            $url = URL::temporarySignedRoute('accountant.export', now()->addDays(7), ['company' => $company->id, 'month' => $closure->month, 'approval' => $closure->approval_version, 'recipient' => hash('sha256', $company->accountant_email)]);
            Notification::route('mail', $company->accountant_email)->notify(new PontajMessage('Pontaj '.$closure->month.' · '.$company->name, 'Pontaj aprobat, pentru verificare și prelucrare de către contabil. Linkul expiră în 7 zile.', $url, 'Descarcă pontajul Excel'));
            DB::table('notification_deliveries')->where('id', $delivery->id)->update(['sent_at' => now(), 'updated_at' => now()]);
        }, 3);
    }
}
