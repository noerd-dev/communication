<?php

declare(strict_types=1);

namespace Noerd\Communication\Support;

use Noerd\Communication\Services\Communicator;
use Symfony\Component\Mime\Email;

/**
 * Tags an outgoing message with its communication row. An invokable object
 * instead of a closure: a queued Mailable is serialised together with its
 * Symfony message callbacks, and closures cannot be serialised.
 */
final class CommunicationHeader
{
    public function __construct(
        public readonly int $communicationId,
    ) {}

    public function __invoke(Email $message): void
    {
        $message->getHeaders()->addTextHeader(Communicator::COMMUNICATION_HEADER, (string) $this->communicationId);
    }
}
