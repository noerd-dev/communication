<?php

namespace Noerd\Communication\Providers;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Noerd\Communication\Commands\CommunicationInstallCommand;
use Noerd\Communication\Commands\CommunicationUpdateCommand;
use Noerd\Communication\Commands\Crons\DeleteOldCommunications;
use Noerd\Communication\Listeners\LogMessageSentFallback;
use Noerd\Communication\Services\Communicator;
use Noerd\Communication\Services\TenantSmtpResolver;
use Noerd\Communication\Support\TextMessageChannelRegistry;
use Noerd\Providers\NoerdModuleServiceProvider;

class CommunicationServiceProvider extends NoerdModuleServiceProvider
{
    protected string $moduleKey = 'communication';

    protected array $commands = [
        CommunicationInstallCommand::class,
        CommunicationUpdateCommand::class,
        DeleteOldCommunications::class,
    ];

    protected function registerModule(): void
    {
        $this->app->singleton(TenantSmtpResolver::class);
        $this->app->singleton(TextMessageChannelRegistry::class);
        $this->app->singleton(Communicator::class);
    }

    protected function bootModule(): void
    {
        Event::listen(MessageSent::class, LogMessageSentFallback::class);
    }
}
