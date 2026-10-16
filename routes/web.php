<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\BillingSubscriptionController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskUserController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FlareSendWebhookController;
use App\Http\Controllers\OperatorEscalationController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceGenerationController;
use App\Http\Controllers\LeadAnalysisController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadMessagingController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MoneyController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SharedInvoiceController;
use App\Http\Controllers\TaxLedgerController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\WhatsAppSettingsController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'store'])->name('whatsapp.webhook.store');
Route::post('webhooks/flaresend', [FlareSendWebhookController::class, 'store'])->name('flaresend.webhook.store');
Route::post('webhook', [FlareSendWebhookController::class, 'store']);
Route::post('webhooks/flare-send', [FlareSendWebhookController::class, 'store']);
Route::post('api/webhook', [FlareSendWebhookController::class, 'store']);
Route::post('api/flaresend', [FlareSendWebhookController::class, 'store']);
Route::post('webhooks/twilio', [TwilioWebhookController::class, 'store'])->name('twilio.webhook.store');

Route::get('share/invoices/{token}', [SharedInvoiceController::class, 'show'])->name('invoices.share');
Route::get('share/invoices/{token}/pdf', [SharedInvoiceController::class, 'pdf'])->name('invoices.share.pdf');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create');
    Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
    Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::post('contacts/{contact}/notes', [ContactController::class, 'note'])->name('contacts.notes.store');
    Route::post('contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/stage', [LeadController::class, 'stage'])->name('leads.stage');
    Route::post('leads/{lead}/notes', [LeadController::class, 'note'])->name('leads.notes.store');
    Route::post('leads/{lead}/interactions', [LeadController::class, 'interaction'])->name('leads.interactions.store');
    Route::post('leads/{lead}/messages', [LeadController::class, 'message'])->name('leads.messages.store');
    Route::put('leads/{lead}/follow-up', [LeadController::class, 'followUp'])->name('leads.follow-up.update');
    Route::put('leads/{lead}/conversation-schedule', [LeadController::class, 'scheduleConversation'])->name('leads.conversation-schedule.update');
    Route::post('leads/{lead}/mark-date', [LeadController::class, 'markDate'])->name('leads.mark-date');
    Route::post('leads/{lead}/whatsapp', [LeadMessagingController::class, 'send'])->name('leads.whatsapp.send');
    Route::post('leads/{lead}/messaging', [LeadMessagingController::class, 'update'])->name('leads.messaging.update');
    Route::post('leads/{lead}/escalations/{escalation}/resolve', [OperatorEscalationController::class, 'resolve'])
        ->name('leads.escalations.resolve');
    Route::post('leads/{lead}/analyses', [LeadAnalysisController::class, 'store'])->name('leads.analyses.store');
    Route::post('leads/{lead}/analyses/{analysis}/apply', [LeadAnalysisController::class, 'apply'])->name('leads.analyses.apply');
    Route::post('leads/{lead}/analyses/{analysis}/stage', [LeadAnalysisController::class, 'stage'])->name('leads.analyses.stage');
    Route::post('leads/{lead}/drafts/{draft}', [LeadAnalysisController::class, 'draft'])->name('leads.drafts.update');

    Route::get('ai', [AiSettingsController::class, 'index'])->name('ai.settings');
    Route::post('ai/articles', [AiSettingsController::class, 'store'])->name('ai.articles.store');
    Route::put('ai/articles/{article}', [AiSettingsController::class, 'update'])->name('ai.articles.update');
    Route::post('ai/gaps/{gap}/answer', [AiSettingsController::class, 'answerGap'])->name('ai.gaps.answer');
    Route::post('ai/auto-replies', [AiSettingsController::class, 'storeRule'])->name('ai.auto-replies.store');
    Route::put('ai/auto-replies/{rule}', [AiSettingsController::class, 'updateRule'])->name('ai.auto-replies.update');
    Route::delete('ai/auto-replies/{rule}', [AiSettingsController::class, 'destroyRule'])->name('ai.auto-replies.destroy');
    Route::post('ai/whatsapp-sync', [AiSettingsController::class, 'syncWhatsApp'])->name('ai.whatsapp-sync');

    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('money', [MoneyController::class, 'index'])->name('money.index');
    Route::post('money/accounts', [MoneyController::class, 'storeAccount'])->name('money.accounts.store');
    Route::put('money/accounts/{account}', [MoneyController::class, 'updateAccount'])->name('money.accounts.update');
    Route::delete('money/accounts/{account}', [MoneyController::class, 'destroyAccount'])->name('money.accounts.destroy');
    Route::post('money/debts', [MoneyController::class, 'storeDebt'])->name('money.debts.store');
    Route::put('money/debts/{debt}', [MoneyController::class, 'updateDebt'])->name('money.debts.update');
    Route::post('money/debts/{debt}/settle', [MoneyController::class, 'settleDebt'])->name('money.debts.settle');
    Route::delete('money/debts/{debt}', [MoneyController::class, 'destroyDebt'])->name('money.debts.destroy');
    Route::post('money/recurrings', [MoneyController::class, 'storeRecurring'])->name('money.recurrings.store');
    Route::put('money/recurrings/{recurring}', [MoneyController::class, 'updateRecurring'])->name('money.recurrings.update');
    Route::post('money/recurrings/{recurring}/paid', [MoneyController::class, 'markRecurringPaid'])->name('money.recurrings.paid');
    Route::delete('money/recurrings/{recurring}', [MoneyController::class, 'destroyRecurring'])->name('money.recurrings.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::put('integrations/{account}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('integrations/{account}/sync', [IntegrationController::class, 'sync'])->name('integrations.sync');
    Route::post('integrations/{account}/help', [IntegrationController::class, 'requestHelp'])->name('integrations.help');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('calendar/{activity}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/{activity}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

    Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index');
    Route::post('follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::put('follow-ups/{rule}', [FollowUpController::class, 'update'])->name('follow-ups.update');
    Route::post('follow-ups/tasks/{task}/cancel', [FollowUpController::class, 'cancel'])->name('follow-ups.cancel');
    Route::post('follow-ups/tasks/{task}/done', [FollowUpController::class, 'complete'])->name('follow-ups.complete');

    Route::get('whatsapp', [WhatsAppSettingsController::class, 'index'])->name('whatsapp.settings');
    Route::put('whatsapp', [WhatsAppSettingsController::class, 'update'])->name('whatsapp.settings.update');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('pipelines', [PipelineController::class, 'index'])->name('pipelines.index');
    Route::post('pipelines', [PipelineController::class, 'store'])->name('pipelines.store');
    Route::get('pipelines/{pipeline}', [PipelineController::class, 'show'])->name('pipelines.show');
    Route::put('pipelines/{pipeline}', [PipelineController::class, 'update'])->name('pipelines.update');
    Route::delete('pipelines/{pipeline}', [PipelineController::class, 'destroy'])->name('pipelines.destroy');
    Route::post('pipelines/{pipeline}/archive', [PipelineController::class, 'archive'])->name('pipelines.archive');
    Route::post('pipelines/{pipeline}/stages', [PipelineController::class, 'storeStage'])->name('pipelines.stages.store');
    Route::put('pipelines/{pipeline}/stages/{stage}', [PipelineController::class, 'updateStage'])->name('pipelines.stages.update');
    Route::post('pipelines/{pipeline}/stages/{stage}/archive', [PipelineController::class, 'archiveStage'])->name('pipelines.stages.archive');
    Route::post('pipelines/{pipeline}/stages/reorder', [PipelineController::class, 'reorder'])->name('pipelines.stages.reorder');
    Route::put('pipelines/{pipeline}/transitions', [PipelineController::class, 'transitions'])->name('pipelines.transitions.update');

    Route::get('users', [DeskUserController::class, 'index'])->name('users.index');
    Route::post('users', [DeskUserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [DeskUserController::class, 'update'])->name('users.update');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices/generate', [InvoiceGenerationController::class, 'storeDraft'])->name('invoices.generate');
    Route::post('invoices/generations/{generation}/create', [InvoiceGenerationController::class, 'linkAndApply'])->name('invoices.generations.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('invoices/{invoice}/mark-unpaid', [InvoiceController::class, 'markUnpaid'])->name('invoices.mark-unpaid');
    Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
    Route::post('invoices/{invoice}/generations', [InvoiceGenerationController::class, 'store'])->name('invoices.generations.store');
    Route::post('invoices/{invoice}/generations/{generation}/apply', [InvoiceGenerationController::class, 'apply'])->name('invoices.generations.apply');

    Route::get('billing/profile', [BusinessProfileController::class, 'edit'])->name('billing.profile.edit');
    Route::post('billing/profile', [BusinessProfileController::class, 'update'])->name('billing.profile.update');

    Route::get('billing/subscriptions', [BillingSubscriptionController::class, 'index'])->name('billing.subscriptions.index');
    Route::post('billing/subscriptions', [BillingSubscriptionController::class, 'store'])->name('billing.subscriptions.store');
    Route::put('billing/subscriptions/{subscription}', [BillingSubscriptionController::class, 'update'])->name('billing.subscriptions.update');
    Route::delete('billing/subscriptions/{subscription}', [BillingSubscriptionController::class, 'destroy'])->name('billing.subscriptions.destroy');
    Route::post('billing/subscriptions/{subscription}/invoice', [BillingSubscriptionController::class, 'invoiceNow'])->name('billing.subscriptions.invoice');
    Route::post('billing/subscriptions/{subscription}/payments', [BillingSubscriptionController::class, 'recordPayment'])->name('billing.subscriptions.payments.store');

    Route::get('billing/tax-log', [TaxLedgerController::class, 'index'])->name('billing.tax-log');
});

require __DIR__.'/settings.php';
