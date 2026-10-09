<?php

declare(strict_types=1);

namespace Noerd\Communication\Tests\Fixtures;

use Noerd\Communication\Contracts\TextMessageDriver;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Support\TextMessage;
use Noerd\Communication\Support\TextMessageResult;
use RuntimeException;

/**
 * A scripted text message driver: records every message and answers with a fixed result,
 * or throws when told to fail.
 */
class ZzTextDriver implements TextMessageDriver
{
    /**
     * @var list<TextMessage>
     */
    public array $sent = [];

    public function __construct(
        public CommunicationType $channel = CommunicationType::Sms,
        public bool $available = true,
        public ?string $failWith = null,
        public TextMessageResult $result = new TextMessageResult(messageId: 'zz-1', from: '+4930000000', units: 2, cost: 0.075, currency: 'EUR'),
    ) {}

    public function type(): CommunicationType
    {
        return $this->channel;
    }

    public function provider(): string
    {
        return 'zz';
    }

    public function isAvailable(?int $tenantId): bool
    {
        return $this->available;
    }

    public function send(TextMessage $message): TextMessageResult
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        $this->sent[] = $message;

        return $this->result;
    }
}
