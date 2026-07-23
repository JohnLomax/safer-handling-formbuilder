<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\FeedbackController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TrainingMatrixController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('login');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('/enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
    Route::post('/enquiries/{enquiry}/booking', [EnquiryController::class, 'updateBooking'])->name('enquiries.booking.update');
    Route::post('/enquiries/{enquiry}/retry/quote-email', [EnquiryController::class, 'retryQuoteEmail'])->name('enquiries.retry.quote-email');
    Route::post('/enquiries/{enquiry}/retry/lead-notification', [EnquiryController::class, 'retryLeadNotification'])->name('enquiries.retry.lead-notification');
    Route::post('/enquiries/{enquiry}/retry/resume-email', [EnquiryController::class, 'retryResumeEmail'])->name('enquiries.retry.resume-email');
    Route::post('/enquiries/{enquiry}/retry/booking-email', [EnquiryController::class, 'retryBookingEmail'])->name('enquiries.retry.booking-email');
    Route::post('/enquiries/{enquiry}/resend/resume-email', [EnquiryController::class, 'resendResumeEmail'])->name('enquiries.resend.resume-email');
    Route::post('/enquiries/{enquiry}/resend/booking-email', [EnquiryController::class, 'resendBookingEmail'])->name('enquiries.resend.booking-email');
    Route::post('/enquiries/{enquiry}/retry/xero-invoice', [EnquiryController::class, 'retryXeroInvoice'])->name('enquiries.retry.xero-invoice');
    Route::post('/enquiries/{enquiry}/sync/xero-invoice-sent', [EnquiryController::class, 'syncXeroInvoiceSent'])->name('enquiries.sync.xero-invoice-sent');
    Route::post('/enquiries/{enquiry}/events/{event}/retry', [EnquiryController::class, 'retryEvent'])->name('enquiries.retry.event');

    Route::get('/feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('/feedback/export', [FeedbackController::class, 'export'])->name('feedback.export');
    Route::patch('/feedback/{feedback}/resolve', [FeedbackController::class, 'resolve'])->name('feedback.resolve');

    Route::resource('users', UserController::class)->except(['show']);

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/xero/connect', [SettingController::class, 'connectXero'])->name('settings.xero.connect');
    Route::get('/settings/xero/callback', [SettingController::class, 'xeroCallback'])->name('settings.xero.callback');
    Route::post('/settings/xero/disconnect', [SettingController::class, 'disconnectXero'])->name('settings.xero.disconnect');

    Route::resource('training-matrix', TrainingMatrixController::class)
        ->parameters(['training-matrix' => 'trainingMatrix'])
        ->except(['show']);
});

Route::get('/dashboard', function () {
    if (! auth()->user()?->is_admin) {
        abort(403, 'Admin access required.');
    }

    return redirect()->route('admin.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
