<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

class SeoMetadata
{
    public function baseUrl(): string
    {
        return rtrim((string) config('seo.url'), '/');
    }

    public function indexable(Request $request): bool
    {
        return (bool) config('seo.indexable') && ! $request->user() && $request->isMethod('get') && $request->routeIs('home', 'legal.contact');
    }

    /** @return list<string> */
    public function head(Request $request): array
    {
        $home = $request->routeIs('home');
        $title = $home ? 'Pontaj simplu. Timp pentru ce contează. · iPontaj' : ($request->routeIs('legal.contact') ? 'Contact și reclamații · iPontaj' : 'iPontaj');
        $description = $request->routeIs('legal.contact') ? 'Contactează echipa iPontaj pentru suport, abonamente și întrebări despre serviciu.' : 'Aplicație de pontaj în limba română pentru echipe mici. Confirmă orele lucrate, verifică luna și exportă pontajul pentru contabil. Plan gratuit, fără card.';
        $url = $this->baseUrl().($home ? '/' : '/'.ltrim($request->path(), '/'));
        $escape = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $meta = function (string $key, string $name, string $content, bool $property = false) use ($escape): string {
            return '<meta data-inertia="'.$key.'" '.($property ? 'property' : 'name').'="'.$escape($name).'" content="'.$escape($content).'">';
        };
        $head = [
            '<title data-inertia="">'.$escape($title).'</title>',
            $meta('seo-description', 'description', $description),
            $meta('seo-robots', 'robots', $this->indexable($request) ? 'index, follow, max-image-preview:large' : 'noindex, nofollow'),
            '<link data-inertia="seo-canonical" rel="canonical" href="'.$escape($url).'">',
            $meta('seo-og-title', 'og:title', $title, true), $meta('seo-og-description', 'og:description', $description, true),
            $meta('seo-og-url', 'og:url', $url, true), $meta('seo-og-type', 'og:type', 'website', true),
            $meta('seo-og-site', 'og:site_name', 'iPontaj', true), $meta('seo-og-locale', 'og:locale', 'ro_RO', true),
            $meta('seo-og-image', 'og:image', $this->baseUrl().'/branding/social-card.png', true),
            $meta('seo-og-image-alt', 'og:image:alt', 'iPontaj — Pontajul la zi. O grijă în minus.', true),
            $meta('seo-twitter-card', 'twitter:card', 'summary_large_image'),
            $meta('seo-twitter-title', 'twitter:title', $title), $meta('seo-twitter-description', 'twitter:description', $description),
            $meta('seo-twitter-image', 'twitter:image', $this->baseUrl().'/branding/social-card.png'),
        ];
        if (config('seo.google_verification')) {
            $head[] = $meta('seo-google-verification', 'google-site-verification', config('seo.google_verification'));
        }
        if ($request->routeIs('home', 'legal.contact')) {
            $head[] = '<link data-inertia="seo-llms" rel="describedby" type="text/plain" href="'.$escape($this->baseUrl().'/llms.txt').'">';
        }
        if ($home) {
            $base = $this->baseUrl();
            $operator = ['@type' => 'Organization', '@id' => $base.'/#operator', 'name' => 'iPontaj', 'legalName' => config('legal.operator.name'), 'url' => $base.'/', 'logo' => $base.'/icons/icon-512.png', 'email' => config('legal.operator.email'), 'telephone' => '+4'.config('legal.operator.phone'), 'taxID' => config('legal.operator.cif')];
            $graph = ['@context' => 'https://schema.org', '@graph' => [
                $operator,
                ['@type' => 'WebSite', '@id' => $base.'/#website', 'name' => 'iPontaj', 'url' => $base.'/', 'inLanguage' => 'ro-RO', 'publisher' => ['@id' => $base.'/#operator']],
                ['@type' => 'WebApplication', '@id' => $base.'/#app', 'name' => 'iPontaj', 'url' => $base.'/', 'description' => $description, 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'inLanguage' => 'ro-RO', 'publisher' => ['@id' => $base.'/#operator'], 'offers' => ['@type' => 'Offer', 'name' => 'Plan Gratuit', 'price' => 0, 'priceCurrency' => 'RON', 'url' => $base.'/register'], 'license' => 'https://www.gnu.org/licenses/agpl-3.0.html'],
            ]];
            $nonce = Vite::cspNonce();
            $head[] = '<script data-inertia="seo-jsonld" type="application/ld+json"'.($nonce ? ' nonce="'.$escape($nonce).'"' : '').'>'.json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).'</script>';
        }

        return $head;
    }
}
