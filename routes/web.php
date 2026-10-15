<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeskUserController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadMessagingController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WhatsAppSettingsController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'store'])->name('whatsapp.webhook.store');

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
    Route::post('contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');

    Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
    Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::post('leads/{lead}/stage', [LeadController::class, 'stage'])->name('leads.stage');
    Route::post('leads/{lead}/notes', [LeadController::class, 'note'])->name('leads.notes.store');
    Route::post('leads/{lead}/messages', [LeadController::class, 'message'])->name('leads.messages.store');
    Route::put('leads/{lead}/follow-up', [LeadController::class, 'followUp'])->name('leads.follow-up.update');
    Route::post('leads/{lead}/whatsapp', [LeadMessagingController::class, 'send'])->name('leads.whatsapp.send');
    Route::post('leads/{lead}/messaging', [LeadMessagingController::class, 'update'])->name('leads.messaging.update');

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
});

require __DIR__.'/settings.php';
