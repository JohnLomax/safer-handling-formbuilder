<?php

use App\Http\Controllers\Api\BrevoWebhookController;
use App\Http\Controllers\Api\EnquiryAttendeesController;
use App\Http\Controllers\Api\TrainingMatrixController;
use App\Http\Controllers\Api\XeroWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/training-matrix', [TrainingMatrixController::class, 'index']);

// Xero developer portal → Webhooks delivery URL (must be public HTTPS).
Route::post('/xero/webhooks', XeroWebhookController::class)->name('api.xero.webhooks');

// Brevo transactional webhooks (opened / uniqueOpened) for enquiry email read status.
Route::post('/brevo/webhooks', BrevoWebhookController::class)->name('api.brevo.webhooks');

// External systems (e.g. Forge): add attendees + create additional Xero invoice (£80/attendee by default).
Route::post('/enquiries/{enquiry}/attendees', [EnquiryAttendeesController::class, 'store'])
    ->name('api.enquiries.attendees.store');