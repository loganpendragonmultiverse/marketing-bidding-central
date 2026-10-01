<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BidController;
use App\Http\Controllers\CategoryRequestController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UtilityController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketplaceController::class, 'home'])->name('home');
Route::get('/categories/{category}', [MarketplaceController::class, 'category'])->name('categories.show');
Route::get('/listings/{listing}', [MarketplaceController::class, 'listing'])->name('listings.show');
Route::get('/go/{listing}', [UtilityController::class, 'go'])->middleware('throttle:go')->name('listings.go');
Route::post('/listings/{listing}/view', [UtilityController::class, 'view'])->middleware('throttle:listing-view')->name('listings.view');
Route::get('/request-category', [CategoryRequestController::class, 'create'])->name('category-requests.create');
Route::post('/request-category', [CategoryRequestController::class, 'store'])->middleware('throttle:category-request')->name('category-requests.store');

Route::get('/submit', [SubmissionController::class, 'create'])->name('submissions.create');
Route::post('/submit', [SubmissionController::class, 'store'])->middleware('throttle:listing-submit')->name('submissions.store');
Route::get('/submission/received', [SubmissionController::class, 'received'])->name('submissions.received');
Route::get('/submission/{listing}/manage', [SubmissionController::class, 'manage'])->name('submissions.manage');
Route::post('/submission/{listing}/manage', [SubmissionController::class, 'update'])->middleware('throttle:listing-submit')->name('submissions.update');

Route::get('/listings/{listing}/bid', [BidController::class, 'preview'])->name('bids.preview');
Route::post('/listings/{listing}/bid', [BidController::class, 'store'])->middleware('throttle:bid')->name('bids.store');
Route::get('/bid/success', [BidController::class, 'success'])->name('bids.success');
Route::get('/bid/status', [BidController::class, 'status'])->middleware('throttle:60,1')->name('bids.status');
Route::post('/listings/{listing}/report', [UtilityController::class, 'report'])->middleware('throttle:report')->name('listings.report');

Route::get('/legal/{page}', [UtilityController::class, 'legal'])->name('legal');
Route::get('/sitemap.xml', [UtilityController::class, 'sitemap'])->name('sitemap');
Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::prefix('customer')->name('customer.')->group(function (): void {
    Route::get('/login', [CustomerAuthController::class, 'login'])->name('login');
    Route::post('/login/code', [CustomerAuthController::class, 'sendCode'])->middleware('throttle:customer-code')->name('send-code');
    Route::post('/login/verify', [CustomerAuthController::class, 'verifyCode'])->middleware('throttle:customer-login')->name('verify-code');
    Route::middleware('auth')->group(function (): void {
        Route::get('/', [CustomerAuthController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    });
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AdminController::class, 'login'])->name('login');
    Route::post('/login', [AdminController::class, 'authenticate'])->middleware('throttle:admin-login')->name('authenticate');
    Route::middleware('marketplace.admin')->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::get('/listings', [AdminController::class, 'listings'])->name('listings.index');
        Route::get('/listings/create', [AdminController::class, 'createListing'])->name('listings.create');
        Route::post('/listings', [AdminController::class, 'storeListing'])->name('listings.store');
        Route::get('/listings/{listing}', [AdminController::class, 'showListing'])->name('listings.show');
        Route::post('/listings/{listing}/enforce', [AdminController::class, 'enforce'])->name('listings.enforce');
        Route::post('/listings/{listing}', [AdminController::class, 'updateListing'])->name('listings.update');
        Route::post('/listings/{listing}/adjust', [AdminController::class, 'adjustListing'])->name('listings.adjust');
        Route::get('/payments', [AdminController::class, 'payments'])->name('payments.index');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports.index');
        Route::get('/category-requests', [AdminController::class, 'categoryRequests'])->name('category-requests.index');
        Route::get('/categories', [AdminController::class, 'categories'])->name('categories.index');
        Route::get('/system', [AdminController::class, 'system'])->name('system.index');
        Route::get('/messages', [ContactController::class, 'index'])->name('messages.index');
        Route::post('/messages/{message}/resolve', [ContactController::class, 'resolve'])->name('messages.resolve');
        Route::post('/categories/{category?}', [AdminController::class, 'saveCategory'])->name('categories.save');
        Route::post('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/reports/{report}/resolve', [AdminController::class, 'resolveReport'])->name('reports.resolve');
        Route::post('/category-requests/{categoryRequest}/resolve', [AdminController::class, 'resolveCategoryRequest'])->name('category-requests.resolve');
        Route::post('/transactions/{transaction}/refund', [AdminController::class, 'refund'])->name('transactions.refund');
    });
});
