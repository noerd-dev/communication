<?php

namespace Noerd\Communication\Commands\Crons;

use Illuminate\Console\Command;
use Noerd\Communication\Models\Communication;

class DeleteOldCommunications extends Command
{
    protected $signature = 'communication:delete-old-communications {--days=30 : Retention period in days}';

    protected $description = 'Delete communications older than the given retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = Communication::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} communications older than {$days} days.");

        return self::SUCCESS;
    }
}
