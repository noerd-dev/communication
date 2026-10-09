<?php

namespace Noerd\Communication\Support;

use Noerd\Communication\Contracts\TextMessageDriver;
use Noerd\Communication\Enums\CommunicationType;

/**
 * Holds ONE driver per text channel. Provider modules register theirs in their provider's
 * boot(); a later registration for the same channel replaces the earlier one.
 */
class TextMessageChannelRegistry
{
    /**
     * @var array<string,TextMessageDriver>
     */
    private array $drivers = [];

    public function register(TextMessageDriver $driver): void
    {
        $this->drivers[$driver->type()->value] = $driver;
    }

    public function driverFor(CommunicationType $type): ?TextMessageDriver
    {
        return $this->drivers[$type->value] ?? null;
    }

    public function isAvailable(CommunicationType $type, ?int $tenantId = null): bool
    {
        return $this->driverFor($type)?->isAvailable($tenantId) ?? false;
    }
}
