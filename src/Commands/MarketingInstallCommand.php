<?php

namespace Noerd\Marketing\Commands;

use Illuminate\Console\Command;
use Noerd\Traits\HasModuleInstallation;
use Noerd\Traits\RequiresNoerdInstallation;

class MarketingInstallCommand extends Command
{
    use HasModuleInstallation;
    use RequiresNoerdInstallation;

    protected $signature = 'noerd:install-marketing {--force : Overwrite existing files without asking}';

    protected $description = 'Install marketing module content and navigation';

    public function handle(): int
    {
        return $this->runModuleInstallation();
    }

    protected function getModuleName(): string
    {
        return 'Marketing';
    }

    protected function getModuleKey(): string
    {
        return 'marketing';
    }

    protected function getDefaultAppTitle(): string
    {
        return 'Marketing';
    }

    protected function getAppIcon(): string
    {
        return 'marketing::icons.app';
    }

    protected function getAppRoute(): string
    {
        return 'communications';
    }

    protected function getSourceDir(): string
    {
        return dirname(__DIR__, 2) . '/app-configs/marketing';
    }
}
