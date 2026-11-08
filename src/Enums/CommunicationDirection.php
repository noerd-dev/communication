<?php

namespace Noerd\Communication\Enums;

enum CommunicationDirection: string
{
    /** A mail or text message the platform sent. */
    case Outbound = 'outbound';

    /** A text message somebody sent to the platform (a reply). */
    case Inbound = 'inbound';
}
