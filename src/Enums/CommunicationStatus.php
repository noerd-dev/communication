<?php

namespace Noerd\Communication\Enums;

enum CommunicationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';

    /** The provider reports the text message arrived on the phone. */
    case Delivered = 'delivered';

    /** The recipient opened it (WhatsApp read receipts). */
    case Read = 'read';

    case Failed = 'failed';

    /** An inbound text message a provider handed over. */
    case Received = 'received';

    /**
     * Whether the provider accepted the message — sent, delivered or read.
     */
    public function wasSent(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read], true);
    }

    /**
     * How far an outbound message got; a provider's status report never moves
     * a message back (reports arrive out of order).
     */
    public function progress(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Sent => 1,
            self::Failed => 2,
            self::Delivered => 3,
            self::Read => 4,
            self::Received => 0,
        };
    }
}
