<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MonthClosure;
use App\Services\CompanyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataController extends Controller
{
    public function export(Request $request, CompanyContext $context): StreamedResponse
    {
        $company = $context->owner($request);
        $data = ['generated_at' => now()->toIso8601String(), 'company' => $company->only(['name', 'cif', 'customer_type', 'registry_verified_at', 'timezone', 'reminders', 'accountant_email']), 'members' => $company->memberships()->with('user:id,first_name,last_name,name,email')->orderBy('id')->get(), 'schedules' => $company->schedules()->get(), 'entries' => $company->entries()->with('intervals')->get(), 'closures' => MonthClosure::where('company_id', $company->id)->get(), 'audit' => AuditEvent::where('company_id', $company->id)->get()];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, 'ipontaj-date-firma.json', ['Content-Type' => 'application/json']);
    }
}
