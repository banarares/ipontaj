<?php

namespace App\Services;

use Illuminate\Support\Str;

class LegalDocuments
{
    /** @return array<string,array{title:string,icon:string}> */
    public function index(): array
    {
        return [
            'termeni-si-conditii' => ['title' => 'Termeni și condiții', 'icon' => 'document'],
            'confidentialitate' => ['title' => 'Confidențialitate', 'icon' => 'shield'],
            'cookies' => ['title' => 'Cookie-uri și stocare locală', 'icon' => 'cookie'],
            'acord-prelucrare-date' => ['title' => 'Acord de prelucrare a datelor', 'icon' => 'users'],
            'furnizori' => ['title' => 'Furnizori și transferuri', 'icon' => 'building'],
            'abonamente-si-anulare' => ['title' => 'Abonamente, anulare și rambursări', 'icon' => 'card'],
            'retragere' => ['title' => 'Retrageți-vă din contract aici', 'icon' => 'undo'],
            'solicitari-date' => ['title' => 'Drepturile tale privind datele', 'icon' => 'usercheck'],
            'contact' => ['title' => 'Contact și reclamații', 'icon' => 'mail'],
            'securitate' => ['title' => 'Securitate și accesibilitate', 'icon' => 'lock'],
            'licenta' => ['title' => 'Licență și cod sursă', 'icon' => 'code'],
        ];
    }

    public function text(string $slug): string
    {
        abort_unless(isset($this->index()[$slug]), 404);

        return file_get_contents(resource_path('legal/'.config('legal.version').'/'.$slug.'.md')) ?: '';
    }

    public function html(string $slug): string
    {
        return Str::markdown($this->text($slug), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /** @return array<string,mixed> */
    public function facts(): array
    {
        return ['operator' => config('legal.operator'), 'hosting' => config('legal.hosting'), 'retention' => config('legal.retention'), 'monthly_price_ron' => 49, 'consumers_allowed' => true];
    }

    /** @param list<string> $slugs
     * @return array<string,array{version:string,sha256:string,text:string}> */
    public function snapshot(array $slugs): array
    {
        $result = [];
        foreach ($slugs as $slug) {
            $text = $this->text($slug);
            $result[$slug] = ['version' => config('legal.version'), 'sha256' => hash('sha256', $text), 'text' => $text];
        }

        return $result;
    }

    /** @return array<string,mixed> */
    public function publicData(): array
    {
        return [
            ...$this->facts(), 'version' => config('legal.version'), 'date' => config('legal.date'),
            'session_minutes' => (int) config('session.lifetime'),
            'remember_days' => 400,
            'links' => collect($this->index())->map(fn ($item, $slug) => [...$item, 'href' => '/'.$slug])->values()->all(),
            'sal_asset' => is_file(public_path(config('legal.anpc_sal_asset'))) ? '/'.config('legal.anpc_sal_asset') : null,
        ];
    }
}
