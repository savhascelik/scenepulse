<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole() && request()->getHost()) {
            $scheme = (request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https') ? 'https' : 'http';
            $rootUrl = $scheme . '://' . request()->getHttpHost();
            config(['app.url' => $rootUrl]);
            \Illuminate\Support\Facades\URL::forceRootUrl($rootUrl);
            \Illuminate\Support\Facades\URL::forceScheme($scheme);
        } elseif ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
