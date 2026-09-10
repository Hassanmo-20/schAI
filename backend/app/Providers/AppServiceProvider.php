<?php

namespace App\Providers;

use App\Models\Task;
use App\Policies\TaskPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Gate::policy(Task::class, TaskPolicy::class);

        // Laravel 11+ no longer ships a default "api" limiter, so authenticated
        // API traffic would otherwise be completely unthrottled. Keyed per user
        // (falling back to IP) so one noisy client cannot starve the others.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Each assistant call hits a paid provider, so it gets a much tighter
        // per-user budget than the rest of the API.
        RateLimiter::for('assistant', fn (Request $request) => [
            Limit::perMinute(10)->by('assistant-min:'.($request->user()?->id ?: $request->ip())),
            Limit::perDay(200)->by('assistant-day:'.($request->user()?->id ?: $request->ip())),
        ]);
    }
}
