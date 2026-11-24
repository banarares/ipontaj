<?php

namespace App\Http\Controllers;

use App\Models\LegalRequest;
use App\Notifications\LegalRequestReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LegalRequestController extends Controller
{
    public function store(Request $request, string $kind): RedirectResponse
    {
        $withdrawal = $kind === 'withdrawal';
        $data = $request->validate([
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email|max:254',
            'contract_reference' => $withdrawal ? 'required|string|max:200' : 'nullable|string|max:200',
            'message' => 'nullable|string|max:2000', 'confirmed' => 'accepted',
            'request_type' => [$withdrawal ? 'nullable' : 'required', Rule::in(['access', 'rectification', 'erasure', 'restriction', 'portability', 'objection', 'other'])],
        ]);
        $received = now();
        $record = LegalRequest::create(['kind' => $withdrawal ? 'withdrawal' : $data['request_type'], 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'name' => trim($data['first_name'].' '.$data['last_name']), 'email' => mb_strtolower($data['email']), 'contract_reference' => $data['contract_reference'] ?? null, 'message' => $data['message'] ?? null, 'received_at' => $received, 'due_at' => $withdrawal ? $received->addDays(14) : $received->addMonthNoOverflow()]);
        // Persist first: delivery errors must never discard a withdrawal declaration.
        try {
            Notification::route('mail', $record->email)->notify(new LegalRequestReceived($record->id));
            Notification::route('mail', config('legal.operator.email'))->notify(new LegalRequestReceived($record->id, true));
        } catch (\Throwable $error) {
            report($error);
        }
        $receipt = URL::temporarySignedRoute('legal.request.receipt', now()->addDays(7), ['legalRequest' => $record->id]);

        return back()->with('legal_request', ['reference' => $record->id, 'received_at' => $record->received_at->toIso8601String(), 'receipt_url' => $receipt]);
    }

    public function receipt(LegalRequest $legalRequest): Response
    {
        $body = "iPontaj — confirmare de primire\nOperator: ".config('legal.operator.name')."\nContact: ".config('legal.operator.email')."\nReferință: ".$legalRequest->id."\nTip: ".$legalRequest->kind."\nPrimită la: ".$legalRequest->received_at->toIso8601String()."\nNume: ".$legalRequest->name."\nEmail: ".$legalRequest->email."\nContract: ".$legalRequest->contract_reference."\n\n".$legalRequest->message."\n\nDeclarația a fost primită. Efectele asupra contractului, plăților sau datelor se comunică separat; această dovadă nu confirmă efectuarea unei rambursări ori ștergeri.\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="ipontaj-solicitare-'.$legalRequest->id.'.txt"', 'Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
