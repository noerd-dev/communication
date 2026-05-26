<?php

namespace Noerd\Marketing\Providers;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Marketing\Commands\Crons\DeleteOldCommunications;
use Noerd\Marketing\Commands\MarketingInstallCommand;
use Noerd\Marketing\Commands\MarketingUpdateCommand;
use Noerd\Marketing\Listeners\LogMessageSentFallback;
use Noerd\Marketing\Services\Communicator;
use Noerd\Marketing\Services\TenantSmtpResolver;

class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantSmtpResolver::class);
        $this->app->singleton(Communicator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'marketing');
        Livewire::addNamespace('marketing', viewPath: __DIR__ . '/../../resources/views/components');
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/marketing-routes.php');

        Event::listen(MessageSent::class, LogMessageSentFallback::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                MarketingInstallCommand::class,
                MarketingUpdateCommand::class,
                DeleteOldCommunications::class,
            ]);
        }
    }
}
