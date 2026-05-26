<?php

namespace Noerd\Marketing\Commands;

class MarketingUpdateCommand extends MarketingInstallCommand
{
    protected $signature = 'noerd:update-marketing {--force : Overwrite existing files without asking}';

    protected $description = 'Update Marketing YML configuration files';

    public function handle(): int
    {
        return $this->runModuleUpdate();
    }
}
