<?php

namespace Noerd\Communication\Commands;

class CommunicationUpdateCommand extends CommunicationInstallCommand
{
    protected $signature = 'noerd:update-communication';

    protected $description = 'Update Communication YML configuration files';
}
