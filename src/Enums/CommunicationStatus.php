<?php

namespace Noerd\Marketing\Enums;

enum CommunicationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
