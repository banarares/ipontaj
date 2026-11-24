<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustCloudflareProxies extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;

    public function handle(Request $request, Closure $next)
    {
        // Resolve config during the request, after Laravel has loaded env/config.
        $this->proxies = config('cloudflare.enabled') ? array_values(config('cloudflare.trusted_proxies')) : [];

        return parent::handle($request, $next);
    }
}
