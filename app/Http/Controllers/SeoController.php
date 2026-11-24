<?php

namespace App\Http\Controllers;

use App\Services\SeoMetadata;
use Symfony\Component\HttpFoundation\Response;

class SeoController extends Controller
{
    public function robots(SeoMetadata $seo): Response
    {
        $body = config('seo.indexable') ? "User-agent: *\nAllow: /\nSitemap: ".$seo->baseUrl()."/sitemap.xml\n" : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SeoMetadata $seo): Response
    {
        $urls = '';
        if (config('seo.indexable')) {
            foreach (['/', '/contact'] as $path) {
                $urls .= '<url><loc>'.htmlspecialchars($seo->baseUrl().$path, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>';
            }
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
