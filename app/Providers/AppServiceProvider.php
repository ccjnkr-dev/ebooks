<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\SimulatedGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, SimulatedGateway::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
