<?php

namespace Noerd\Communication\Commands;

use Illuminate\Console\Command;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;

class CommunicationInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-communication {--force : Overwrite existing files without asking}';

    protected $description = 'Install communication module content and navigation';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Communication';
    }

    protected function getModuleKey(): string
    {
        return 'communication';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Communication';
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
