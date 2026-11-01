<?php

namespace Noerd\Communication\Commands;

use Illuminate\Console\Command;
use Noerd\Commands\Concerns\HasModuleInstallation;

class CommunicationInstallCommand extends Command
{
    use HasModuleInstallation;

    protected $signature = 'noerd:install-communication';

    protected $description = 'Install communication module content and navigation';

    protected function getModuleName(): string
    {
        return 'Communication';
    }

    protected function getModuleKey(): string
    {
        return 'communication';
    }

    protected function getAppIcon(): string
    {
        return 'communication::icons.app';
    }

    protected function getAppRoute(): string
    {
        return 'communications';
    }

    protected function getSourceDir(): string
    {
        return dirname(__DIR__, 2) . '/app-configs/communication';
    }
}
