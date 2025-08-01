<?php

namespace App\Providers;

use App\Models\CurrencyRate;
use App\Observers\CurrencyRateObserver;
use App\Repositories\AirportDataRepository;
use App\Repositories\AirportDataRepositoryInterface;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AirportDataRepositoryInterface::class, AirportDataRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CurrencyRate::observe(CurrencyRateObserver::class);

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });
    }
}
