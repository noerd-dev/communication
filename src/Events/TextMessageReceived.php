<?php

namespace Noerd\Communication\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Noerd\Communication\Models\Communication;

/**
 * An inbound text message was logged. Fired inside the TenantContext of the
 * message's tenant (none when no outbound message to the sender was found), so a
 * listener reads and writes tenant data without a context of its own.
 *
 * `contact` and `model` are those of the outbound message it answers
 * (`in_reply_to_id`) — that is how a module recognises a reply to its message.
 */
class TextMessageReceived
{
    use Dispatchable;

    public function __construct(public Communication $communication) {}
}
