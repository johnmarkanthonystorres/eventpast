<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Controllers\PaymentSettingsController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public content pages (from Strapi)
Route::prefix('content')->group(function () {
    Route::get('/pages/{slug}', [ContentController::class, 'showLandingPage'])->name('content.pages.show');
    Route::get('/blog', [ContentController::class, 'blogIndex'])->name('content.blog.index');
    Route::get('/blog/{slug}', [ContentController::class, 'showBlogPost'])->name('content.blog.show');
    Route::get('/help', [ContentController::class, 'helpCenter'])->name('content.help');
    Route::get('/playbooks', [ContentController::class, 'playbooks'])->name('content.playbooks');
});

// Public event pages (with tenant middleware)
Route::middleware(['tenant'])->group(function () {
    // Public pages live under /e/ because /events/{x} belongs to the organizer
    // dashboard; sharing one URI made Laravel drop the public page entirely.
    Route::get('/e/{event:slug}', [RegistrationController::class, 'show'])->name('public.events.show');
    Route::post('/e/{event:slug}/register', [RegistrationController::class, 'store'])->name('public.events.register');
    Route::get('/registrations/{registration:qr_token}/confirmation', [RegistrationController::class, 'confirmation'])->name('registrations.confirmation');
    Route::get('/registrations/{registration:qr_token}/qrcode', [RegistrationController::class, 'qrcode'])->name('registrations.qrcode');
});

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [EventController::class, 'index'])->name('dashboard');
    
    // Events
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    
    // Payment settings (Stripe Connect)
    Route::get('/settings/payments', [PaymentSettingsController::class, 'show'])->name('settings.payments');
    Route::get('/settings/payments/connect', [PaymentSettingsController::class, 'connect'])->name('settings.payments.connect');
    Route::post('/settings/payments/connect', [PaymentSettingsController::class, 'connect']);
    Route::get('/settings/payments/return', [PaymentSettingsController::class, 'return'])->name('settings.payments.return');

    // Tickets
    Route::get('/events/{event}/tickets', [TicketTypeController::class, 'index'])->name('events.tickets.index');
    Route::post('/events/{event}/tickets', [TicketTypeController::class, 'store'])->name('events.tickets.store');
    Route::put('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'update'])->name('events.tickets.update');
    Route::delete('/events/{event}/tickets/{ticketType}', [TicketTypeController::class, 'destroy'])->name('events.tickets.destroy');

    // Check-in
    Route::get('/events/{event}/check-in', [CheckInController::class, 'index'])->name('events.checkin');
    Route::post('/check-in/scan', [CheckInController::class, 'scan'])->name('checkin.scan');
    
    // Analytics placeholder
    Route::get('/analytics', function () {
        return Inertia::render('Analytics/Index');
    })->name('analytics.index');
    
    // Referral Analytics
    Route::get('/analytics/referrals', [App\Http\Controllers\ReferralAnalyticsController::class, 'leaderboard'])->name('analytics.referrals');
    Route::get('/events/{event}/referrals', [App\Http\Controllers\ReferralAnalyticsController::class, 'show'])->name('events.referrals');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
