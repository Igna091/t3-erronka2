<?php

namespace App\Providers;

use App\Support\Basket;
use App\Support\OpeningHours;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Basket::class, fn ($app) => new Basket($app['session.store']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enough card and code attempts for a person, too few to try card numbers by script
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(10)->by($request->session()->getId()));

        // Every page header shows whether the pharmacy is open and how full the basket is
        View::composer('partials.header', function ($view) {
            $view->with([
                'status' => OpeningHours::fromConfig()->status(),
                'basketCount' => app(Basket::class)->count(),
            ]);
        });
    }
}
