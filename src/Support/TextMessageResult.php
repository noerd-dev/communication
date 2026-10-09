<?php

namespace Noerd\Communication\Support;

/**
 * What a driver reports after a text message was accepted by the provider.
 * `cost` stays null when the provider prices the message later.
 */
final readonly class TextMessageResult
{
    public function __construct(
        public ?string $messageId = null,
        public ?string $from = null,
        public int $units = 1,
        public ?float $cost = null,
        public ?string $currency = null,
    ) {}
}
