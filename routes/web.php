<?php

// Item 11/12 — publiek
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SubscriberController;
use App\Http\Controllers\Admin\VideoReviewController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\MeetingController;
use App\Http\Controllers\Public\MunicipalityController;
use App\Http\Controllers\Public\NewsletterWebController;
use App\Http\Controllers\Public\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Item 13 — admin
Route::middleware(['auth', 'is_admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/review', [ReviewController::class, 'index'])->name('review.index');
    Route::get('/review/{meeting}', [ReviewController::class, 'show'])->name('review.show');
    Route::patch('/summary/{summary}', [ReviewController::class, 'update'])->name('review.update');
    Route::post('/review/{meeting}/approve', [ReviewController::class, 'approve'])->name('review.approve');
    Route::get('/subscribers/export', [SubscriberController::class, 'export'])->name('subscribers.export');
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
    Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
    Route::get('/videos', [VideoReviewController::class, 'index'])->name('videos.index');
    Route::post('/videos/{video}/confirm', [VideoReviewController::class, 'confirm'])->name('videos.confirm');
});

Route::get('/', LandingController::class)->name('home');

Route::get('/nieuwsbrief/{newsletter}', [NewsletterWebController::class, 'show'])->name('newsletter.web');

Route::post('/aanmelden', [SubscriptionController::class, 'store'])->name('subscription.store');
Route::get('/bevestig/{token}', [SubscriptionController::class, 'confirm'])->name('subscription.confirm');
Route::get('/uitschrijven/{token}', [SubscriptionController::class, 'unsubscribe'])->name('subscription.unsubscribe');

Route::get('/{municipality:slug}', [MunicipalityController::class, 'show'])->name('municipality.show');
Route::get('/{municipality:slug}/archief', [MunicipalityController::class, 'archive'])->name('municipality.archive');
Route::get('/{municipality:slug}/vergadering/{meeting}', [MeetingController::class, 'show'])
    ->name('meeting.show')
    ->scopeBindings();
