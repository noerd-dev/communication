<?php

namespace Noerd\Communication\Enums;

enum CommunicationType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    /**
     * Whether the channel carries a short text message (sent through a TextMessageDriver).
     */
    public function isTextMessage(): bool
    {
        return $this !== self::Email;
    }
}
