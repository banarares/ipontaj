<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    /** @return Builder<UserNotification> */
    private function visible(Request $request): Builder
    {
        $companies = $request->user()->memberships()->where('active', true)->select('company_id');

        return UserNotification::where('user_id', $request->user()->id)->where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', $companies));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->visible($request);
        $response = response()->json(['unread' => (clone $query)->whereNull('read_at')->count(), 'items' => $query->latest('id')->limit(30)->get(['id', 'title', 'message', 'url', 'created_at', 'read_at'])]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $item = $this->visible($request)->findOrFail($notification);
        $item->update(['read_at' => $item->read_at ?? now()]);

        return back();
    }

    public function open(Request $request, int $notification): RedirectResponse
    {
        $item = $this->visible($request)->findOrFail($notification);
        $item->update(['read_at' => $item->read_at ?? now()]);
        if ($item->company_id !== null) {
            $request->session()->put('company_id', $item->company_id);
        }
        abort_unless(str_starts_with($item->url, '/') && ! str_starts_with($item->url, '//'), 400);

        return redirect($item->url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->visible($request)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
