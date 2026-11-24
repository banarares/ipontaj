<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        if ($request->user() && ! $request->user()->is_demo) {
            return redirect('/dashboard');
        }
        if ($request->user()?->is_demo) {
            return redirect('/dashboard');
        }
        $user = DB::transaction(function () {
            $suffix = Str::random(16);
            $user = User::create(['first_name' => 'Andrei', 'last_name' => 'Popescu', 'email' => 'demo-'.$suffix.'@example.test', 'password' => Str::random(40), 'is_demo' => true]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $company = Company::create(['name' => 'Atelier Studio SRL', 'is_demo' => true]);
            $start = now('Europe/Bucharest')->startOfMonth();
            $today = now('Europe/Bucharest')->startOfDay();
            $company->schedules()->create(['effective_from' => $start->toDateString(), 'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00']);
            $names = [[$user, 'owner'], [User::create(['first_name' => 'Maria', 'last_name' => 'Ionescu', 'email' => 'maria-'.$suffix.'@example.test', 'password' => Str::random(40), 'is_demo' => true]), 'employee'], [User::create(['first_name' => 'Vlad', 'last_name' => 'Dumitrescu', 'email' => 'vlad-'.$suffix.'@example.test', 'password' => Str::random(40), 'is_demo' => true]), 'employee'], [User::create(['first_name' => 'Ioana', 'last_name' => 'Marinescu', 'email' => 'ioana-'.$suffix.'@example.test', 'password' => Str::random(40), 'is_demo' => true]), 'employee']];
            foreach ($names as $i => [$person,$role]) {
                $member = $company->memberships()->create(['user_id' => $person->id, 'role' => $role, 'joined_on' => $start->toDateString()]);
                for ($d = Carbon::parse($start); $d <= $today; $d = $d->addDay()) {
                    if ($d->isWeekend() || ($i === 0 && $d->isSameDay($today)) || ($i === 2 && $d->isSameDay($today)) || ($i === 2 && $d->isSameDay($today->subDay()))) {
                        continue;
                    }
                    $entry = $member->entries()->create(['company_id' => $company->id, 'date' => $d->toDateString(), 'minutes' => 480, 'confirmed_at' => now(), 'confirmed_by' => $person->id, 'absence' => $i === 3 && $d->isSameDay($today) ? 'CO' : null]);
                    if ($entry->absence) {
                        $entry->update(['minutes' => 0]);
                    } else {
                        $entry->intervals()->create(['kind' => 'work', 'start_at' => $d->setTime(9, 0)->utc(), 'end_at' => $d->setTime(17, 0)->utc()]);
                    }
                }
            }

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/dashboard')->with('success', 'Explorează firma demo. Datele sunt fictive și sunt șterse automat.');
    }
}
