<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LegalAcceptance;
use App\Services\LegalAgreements;
use App\Services\LegalDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LegalController extends Controller
{
    public function show(string $slug, LegalDocuments $documents): Response
    {
        abort_unless(isset($documents->index()[$slug]), 404);

        return Inertia::render('Legal', ['document' => [...$documents->index()[$slug], 'slug' => $slug, 'html' => $documents->html($slug)]]);
    }

    public function download(string $slug, LegalDocuments $documents): HttpResponse
    {
        $body = $documents->index()[$slug]['title'] ?? abort(404);
        $body .= "\n\n".json_encode($documents->facts(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n\nVersiunea ".config('legal.version')."\n\n".$documents->text($slug);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="ipontaj-'.$slug.'-'.config('legal.version').'.txt"']);
    }

    public function agreements(Request $request): Response
    {
        $memberships = $request->user()->memberships()->where('active', true)->where('role', 'owner')->with('company')->get();

        return Inertia::render('LegalAgreements', ['companies' => $memberships->map(fn ($member) => $member->company->only(['id', 'name', 'customer_type']))]);
    }

    public function accept(Request $request, LegalAgreements $agreements): RedirectResponse
    {
        abort_if($request->user()->is_demo, 403);
        $data = $request->validate(['terms_accepted' => 'accepted', 'legal_version' => ['required', Rule::in([config('legal.version')])], 'company_id' => 'nullable|integer']);
        $company = isset($data['company_id']) ? Company::whereHas('memberships', fn ($q) => $q->where('user_id', $request->user()->id)->where('active', true)->where('role', 'owner'))->whereKey($data['company_id'])->firstOrFail() : null;
        if ($company && $company->customer_type !== 'consumer') {
            $request->validate(['dpa_accepted' => 'accepted']);
        }
        DB::transaction(function () use ($request, $agreements, $company) {
            $agreements->record($request->user(), 'account', ['termeni-si-conditii', 'confidentialitate']);
            if ($company) {
                $agreements->record($request->user(), 'company', $company->customer_type === 'consumer' ? ['termeni-si-conditii', 'confidentialitate'] : ['acord-prelucrare-date', 'furnizori'], $company);
            }
        });

        return redirect('/dashboard')->with('success', 'Documentele acceptate au fost înregistrate. Poți descărca dovada din Contul meu.');
    }

    public function receipt(Request $request, LegalAcceptance $acceptance): HttpResponse
    {
        abort_unless($acceptance->user_id === $request->user()->id, 404);
        $body = "iPontaj — dovada acceptării\nReferință: ".$acceptance->id."\nData: ".$acceptance->accepted_at->toIso8601String()."\nScop: ".$acceptance->purpose."\n\n".json_encode($acceptance->facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($acceptance->documents as $slug => $document) {
            $body .= "\n\n".$slug.' · '.$document['version'].' · SHA-256 '.$document['sha256']."\n\n".$document['text'];
        }

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="ipontaj-acord-'.$acceptance->id.'.txt"']);
    }
}
