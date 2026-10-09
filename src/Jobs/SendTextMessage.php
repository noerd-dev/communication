<?php

namespace Noerd\Communication\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;

/**
 * Delivers a queued text message that Communicator::sendText() logged before.
 */
class SendTextMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $communicationId) {}

    public function handle(Communicator $communicator): void
    {
        $communication = Communication::withoutGlobalScopes()->find($this->communicationId);

        if ($communication === null) {
            return;
        }

        $communicator->deliverText($communication);
    }
}
