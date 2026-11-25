<?php

namespace App\Console\Commands;

use App\Services\SeoMetadata;
use Illuminate\Console\Command;

class SeoStatusCommand extends Command
{
    protected $signature = 'pontaj:seo:status';

    protected $description = 'Verifică local configurarea indexării, fără cereri de rețea sau acreditări';

    public function handle(SeoMetadata $seo): int
    {
        $shadowFiles = array_values(array_filter(['robots.txt', 'sitemap.xml', 'llms.txt', 'llm.txt'], fn (string $name) => is_file(public_path($name))));
        $this->line(json_encode([
            'indexing_enabled' => (bool) config('seo.indexable'),
            'configuration_cached' => app()->configurationIsCached(),
            'canonical_url' => $seo->baseUrl(),
            'google_verification_configured' => filled(config('seo.google_verification')),
            'robots_policy' => config('seo.indexable') ? 'Allow: /' : 'Disallow: /',
            'sitemap_urls' => config('seo.indexable') ? [$seo->baseUrl().'/', $seo->baseUrl().'/contact'] : [],
            'llms_url' => $seo->baseUrl().'/llms.txt',
            'llms_template_present' => is_file(resource_path('seo/llms.md')),
            'public_files_shadowing_routes' => $shadowFiles,
            'checks_live_server_or_cloudflare' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
