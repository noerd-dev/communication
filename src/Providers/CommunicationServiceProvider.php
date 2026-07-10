<?php

namespace Noerd\Communication\Providers;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Noerd\Communication\Commands\Crons\DeleteOldCommunications;
use Noerd\Communication\Commands\CommunicationInstallCommand;
use Noerd\Communication\Commands\CommunicationUpdateCommand;
use Noerd\Communication\Listeners\LogMessageSentFallback;
use Noerd\Communication\Services\Communicator;
use Noerd\Communication\Services\TenantSmtpResolver;

class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantSmtpResolver::class);
        $this->app->singleton(Communicator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'communication');
        Livewire::addNamespace('communication', viewPath: __DIR__ . '/../../resources/views/components');
        Livewire::addLocation(viewPath: __DIR__ . '/../../resources/views/components');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../../resources/lang');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/communication-routes.php');

        Event::listen(MessageSent::class, LogMessageSentFallback::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CommunicationInstallCommand::class,
                CommunicationUpdateCommand::class,
                DeleteOldCommunications::class,
            ]);
        }
    }
}
