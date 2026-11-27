<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Conversion events confirmed by the server, delivered once by the browser through GTM
 * and only if the visitor accepted optional analytics.
 */
class AnalyticsEvents
{
    public const SESSION_KEY = 'analytics.events';

    /** Guest pages without tokens or personal data in the URL. */
    public const PUBLIC_ROUTES = ['home', 'legal.*', 'login', 'register', 'password.request'];

    public static function allowedOn(Request $request): bool
    {
        return ! $request->user() && $request->routeIs(...self::PUBLIC_ROUTES) && ! $request->routeIs('legal.request.receipt', 'legal.download', 'legal.source');
    }

    /**
     * @param  array<string, scalar>  $params
     * @param  array<string, mixed>|null  $ecommerce
     */
    public function queue(Request $request, string $event, array $params = [], ?array $ecommerce = null): void
    {
        if (! config('analytics.google_tag_manager_id') || ! $request->hasSession()) {
            return;
        }
        $events = array_slice($request->session()->get(self::SESSION_KEY, []), -9);
        $events[] = array_filter(['id' => (string) Str::uuid(), 'event' => $event, 'params' => $params ?: null, 'ecommerce' => $ecommerce], fn ($value) => $value !== null);
        $request->session()->put(self::SESSION_KEY, $events);
    }

    /** @return list<array<string, mixed>> */
    public function pull(Request $request): array
    {
        return $request->hasSession() ? array_values($request->session()->pull(self::SESSION_KEY, [])) : [];
    }

    /** @return array<string, mixed> */
    public static function teamPlan(?string $transactionId = null): array
    {
        $price = (int) config('ipontaj.plans.team.price');

        return array_filter(['transaction_id' => $transactionId, 'currency' => 'RON', 'value' => $price, 'items' => [['item_id' => 'team', 'item_name' => 'iPontaj Echipă', 'price' => $price, 'quantity' => 1]]], fn ($value) => $value !== null);
    }
}
