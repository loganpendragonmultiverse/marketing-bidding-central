<?php

namespace App\Providers;

use App\Payments\NullPaymentGateway;
use App\Payments\PaymentGatewayInterface;
use App\Payments\StripePaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            return filled(config('services.stripe.secret')) && filled(config('services.stripe.webhook_secret'))
                ? new StripePaymentGateway
                : new NullPaymentGateway;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }

        RateLimiter::for('listing-submit', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('bid', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
        RateLimiter::for('report', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('go', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('category-request', fn (Request $request) => Limit::perHour(4)->by($request->ip()));
        RateLimiter::for('listing-view', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('customer-code', fn (Request $request) => Limit::perHour(5)->by(strtolower((string) $request->input('email', $request->ip()))));
        RateLimiter::for('customer-login', fn (Request $request) => Limit::perMinute(8)->by(strtolower((string) $request->input('email', $request->ip()))));
    }
}
