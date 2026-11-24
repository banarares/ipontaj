<?php

namespace Tests\Feature;

use App\Jobs\SendAccountantExport;
use App\Models\Company;
use App\Models\MonthClosure;
use App\Models\User;
use App\Notifications\PontajMessage;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountantExportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-23 12:00', 'Europe/Bucharest'));
        Notification::fake();
        Queue::fake();
        $owner = User::factory()->create();
        $company = Company::create(['name' => 'Firma pentru contabil', 'timezone' => 'Europe/Bucharest', 'accountant_email' => 'contabil@example.test', 'accountant_confirmed_at' => now()]);
        $company->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'tracked' => false, 'joined_on' => '2026-01-01']);
        $company->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_accountant_fixture', 'stripe_status' => 'active', 'stripe_price' => 'price_fixture']);
        app(AttendanceService::class)->close($company, '2026-10', $owner->id);
        $closure = MonthClosure::first();

        return [$owner, $company, $closure];
    }

    private function link(Company $company, MonthClosure $closure): string
    {
        return URL::temporarySignedRoute('accountant.export', now()->addDays(7), ['company' => $company->id, 'month' => $closure->month, 'approval' => $closure->approval_version, 'recipient' => hash('sha256', $company->accountant_email)]);
    }

    public function test_accountant_delivery_is_deduplicated_and_signed_link_expires(): void
    {
        [$owner,$company,$closure] = $this->fixture();
        $job = new SendAccountantExport($closure->id);
        $job->handle();
        $job->handle();
        Notification::assertSentOnDemandTimes(PontajMessage::class, 1);
        $url = $this->link($company, $closure);
        $this->get($url)->assertDownload('ipontaj-2026-10.xlsx');
        $this->travel(8)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_reopening_invalidates_old_links_even_if_reapproved_in_same_second(): void
    {
        [$owner,$company,$closure] = $this->fixture();
        $url = $this->link($company, $closure);
        $service = app(AttendanceService::class);
        $service->close($company, '2026-10', $owner->id, 'Corecție verificată', true);
        $this->get($url)->assertForbidden();
        $service->close($company, '2026-10', $owner->id);
        $this->get($url)->assertForbidden();
        $closure->refresh();
        $this->assertSame(2, $closure->approval_version);
        $this->get($this->link($company, $closure))->assertDownload();
    }

    public function test_unconfirmed_recipient_and_demo_do_not_receive_exports(): void
    {
        [$owner,$company,$closure] = $this->fixture();
        $company->update(['accountant_confirmed_at' => null]);
        (new SendAccountantExport($closure->id))->handle();
        Notification::assertNothingSent();
        $company->update(['accountant_confirmed_at' => now(), 'is_demo' => true]);
        (new SendAccountantExport($closure->id))->handle();
        Notification::assertNothingSent();
    }

    public function test_signed_company_identifier_cannot_be_replaced(): void
    {
        [$owner,$company,$closure] = $this->fixture();
        $other = Company::create(['name' => 'Altă firmă']);
        $url = $this->link($company, $closure);
        $this->get(str_replace('/'.$company->id.'/2026-10', '/'.$other->id.'/2026-10', $url))->assertForbidden();
    }
}
