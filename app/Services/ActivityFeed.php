<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;

class ActivityFeed
{
    public function personal(User $user, string $title, string $url = '/dashboard'): void
    {
        if ($user->is_demo || ! $user->hasVerifiedEmail()) {
            return;
        }
        UserNotification::create(['user_id' => $user->id, 'title' => $title, 'message' => 'Activitate în contul tău iPontaj.', 'url' => $url]);
    }

    public function record(Company $company, ?int $actor, string $title, string $url = '/dashboard', bool $owners = false, ?int $target = null): void
    {
        $recipients = array_filter([$actor, $target]);
        if ($owners) {
            $recipients = [...$recipients, ...$company->memberships()->where('role', 'owner')->where('active', true)->whereNotNull('user_id')->pluck('user_id')->all()];
        }
        $recipients = $company->memberships()->where('active', true)->whereIn('user_id', array_unique($recipients))->pluck('user_id')->all();
        foreach ($recipients as $id) {
            UserNotification::create(['company_id' => $company->id, 'user_id' => $id, 'title' => $title, 'message' => 'În echipa '.$company->name.'.', 'url' => $url]);
        }
    }

    public function audit(AuditEvent $event): void
    {
        $company = Company::find($event->company_id);
        if (! $company) {
            return;
        }
        $data = $event->after ?? [];
        $memberId = $data['membership_id'] ?? null;
        $target = is_int($memberId) ? $company->memberships()->find($memberId)?->user_id : null;
        $date = is_string($data['date'] ?? null) ? substr($data['date'], 0, 10) : null;
        $month = $data['month'] ?? ($date ? substr($date, 0, 7) : null);
        $url = $date ? '/today?date='.$date : '/dashboard';
        if ($month && in_array($event->action, ['Lună aprobată', 'Lună redeschisă'])) {
            $url = '/history?month='.$month;
        }
        $this->record($company, $event->user_id, $event->action, $url, ! str_contains($event->action, 'Export'), $target);
    }
}
