<?php

namespace App\Providers;

use App\Contracts\AiServiceContract;
use App\Integrations\Flask\FlaskApiService;
use App\Services\MockAiService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            AiServiceContract::class,
            function ($app) {
                if (config('services.flask.base_url') && config('app.env') !== 'testing') {
                    return $app->make(FlaskApiService::class);
                }

                return $app->make(MockAiService::class);
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
