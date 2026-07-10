<?php

namespace Noerd\Communication\Commands;

class CommunicationUpdateCommand extends CommunicationInstallCommand
{
    protected $signature = 'noerd:update-communication {--force : Overwrite existing files without asking}';

    protected $description = 'Update Communication YML configuration files';

    public function handle(): int
    {
        return $this->runModuleUpdate();
    }
}
