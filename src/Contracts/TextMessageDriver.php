<?php

namespace Noerd\Communication\Contracts;

use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Support\TextMessage;
use Noerd\Communication\Support\TextMessageResult;

/**
 * Delivers text messages (SMS, WhatsApp, ...) for ONE channel. Provider modules implement it
 * and register the driver on the TextMessageChannelRegistry — this module knows no provider.
 */
interface TextMessageDriver
{
    /**
     * The channel this driver delivers.
     */
    public function type(): CommunicationType;

    /**
     * A stable provider key, stored with the log row and the usage record.
     */
    public function provider(): string;

    /**
     * Whether the driver can send for the given tenant (credentials and sender configured).
     */
    public function isAvailable(?int $tenantId): bool;

    /**
     * Send the message and report what the provider answered. Throws on any failure.
     */
    public function send(TextMessage $message): TextMessageResult;
}
