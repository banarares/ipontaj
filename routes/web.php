<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyLookupController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LegalRequestController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WorkspaceController;
use App\Models\AuditEvent;
use App\Models\Company;
use App\Models\MonthClosure;
use App\Services\ExcelService;
use App\Services\LegalDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,60,contact')->name('contact.store');
foreach (array_keys(app(LegalDocuments::class)->index()) as $slug) {
    Route::get('/'.$slug, [LegalController::class, 'show'])->defaults('slug', $slug)->name('legal.'.$slug);
}
Route::get('/documente/{slug}', [LegalController::class, 'download'])->name('legal.download');
Route::post('/retragere', [LegalRequestController::class, 'store'])->defaults('kind', 'withdrawal')->middleware('throttle:5,60,legal-request');
Route::post('/solicitari-date', [LegalRequestController::class, 'store'])->defaults('kind', 'data')->middleware('throttle:5,60,legal-request');
Route::get('/confirmare-solicitare/{legalRequest}', [LegalRequestController::class, 'receipt'])->middleware(['signed', 'throttle:20,1,legal-receipt'])->name('legal.request.receipt');
Route::get('/cod-sursa', function () {
    abort_unless(is_file(config('legal.source_archive')), 503, 'Arhiva sursei se pregătește. Solicită sursa la office@ipontaj.ro.');

    return response()->download(config('legal.source_archive'), 'ipontaj-sursa.zip');
})->middleware('throttle:5,1,source-download')->name('legal.source');
Route::middleware('auth')->group(function () {
    Route::get('/acorduri', [LegalController::class, 'agreements']);
    Route::post('/acorduri', [LegalController::class, 'accept']);
    Route::get('/acorduri/{acceptance}', [LegalController::class, 'receipt']);
});
Route::post('/demo/exit', function (Request $request) {
    if ($request->user()?->is_demo) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return redirect('/register');
})->name('demo.exit');
Route::post('/demo', [DemoController::class, 'start'])->middleware('throttle:5,60,demo')->name('demo');
Route::get('/invite/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1,invite-view')->name('invitation.show');
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');
Route::middleware(['auth', 'throttle:120,1,workspace'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    foreach (['today', 'history', 'team', 'month', 'settings', 'billing'] as $section) {
        Route::get('/'.$section, fn (Request $request) => app(WorkspaceController::class)->index($request, $section))->name('workspace.'.$section);
    }
    Route::get('/company-lookup', CompanyLookupController::class)->middleware('throttle:10,1,company-lookup');
    Route::get('/onboarding', [WorkspaceController::class, 'onboarding'])->name('onboarding');
    Route::post('/companies', [WorkspaceController::class, 'createCompany']);
    Route::post('/companies/switch', [WorkspaceController::class, 'switchCompany']);
    Route::post('/entries', [WorkspaceController::class, 'saveDay']);
    Route::post('/entries/{entry}/review', [WorkspaceController::class, 'review']);
    Route::post('/months/close', [WorkspaceController::class, 'closeMonth']);
    Route::get('/company/data', [DataController::class, 'export'])->middleware('throttle:5,1,company-data');
    Route::get('/exports', [WorkspaceController::class, 'export'])->middleware('throttle:10,1,exports')->name('attendance.download');
    Route::post('/company/settings', [WorkspaceController::class, 'settings']);
    Route::post('/schedules', [WorkspaceController::class, 'schedule']);
    Route::patch('/members/{member}', [WorkspaceController::class, 'member']);
    Route::post('/members', [InvitationController::class, 'managed'])->middleware('throttle:10,1,managed-members');
    Route::post('/preferences', [WorkspaceController::class, 'preferences']);
    Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1,invitations');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'revoke']);
    Route::post('/invite/{token}', [InvitationController::class, 'accept']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::post('/billing/portal', [BillingController::class, 'portal']);
});
Route::get('/accountant-export/{company}/{month}', function (Request $request, Company $company, string $month) {
    abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);
    $closure = MonthClosure::where('company_id', $company->id)->where('month', $month)->firstOrFail();
    abort_unless($closure->approved_at && (string) $request->query('approval') === (string) $closure->approval_version && $company->accountant_confirmed_at && hash_equals(hash('sha256', $company->accountant_email ?? ''), (string) $request->query('recipient')), 403);
    $path = app(ExcelService::class)->build($company, $month);
    AuditEvent::create(['company_id' => $company->id, 'action' => 'Descărcare contabil', 'after' => ['month' => $month]]);

    return response()->download($path, 'ipontaj-'.$month.'.xlsx')->deleteFileAfterSend(true);
})->middleware(['signed', 'throttle:10,1,exports'])->name('accountant.export');
require __DIR__.'/settings.php';
