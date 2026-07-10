<?php

namespace Noerd\Communication\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LogMessageSentFallback
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();

        $headerValue = $headers->has(Communicator::COMMUNICATION_HEADER)
            ? $headers->get(Communicator::COMMUNICATION_HEADER)?->getBodyAsString()
            : null;

        if ($headerValue !== null) {
            $this->updateExisting((int) $headerValue, $message);

            return;
        }

        $this->logUntracked($message);
    }

    private function updateExisting(int $communicationId, Email $message): void
    {
        $communication = Communication::withoutGlobalScopes()->find($communicationId);

        if ($communication === null) {
            $this->logUntracked($message);

            return;
        }

        $communication->forceFill([
            'status' => CommunicationStatus::Sent,
            'from' => $this->firstAddress($message->getFrom()),
            'subject' => $message->getSubject(),
            'body' => $this->extractBody($message),
            'message_id' => $this->extractMessageId($message),
            'sent_at' => $communication->sent_at ?? now(),
        ])->save();
    }

    private function logUntracked(Email $message): void
    {
        Communication::create([
            'tenant_id' => null,
            'customer_id' => null,
            'type' => CommunicationType::Email,
            'status' => CommunicationStatus::Sent,
            'from' => $this->firstAddress($message->getFrom()),
            'to' => $this->joinAddresses($message->getTo()),
            'subject' => $message->getSubject(),
            'body' => $this->extractBody($message),
            'message_id' => $this->extractMessageId($message),
            'mailable_class' => null,
            'metadata' => null,
            'sent_at' => now(),
        ]);
    }

    private function extractMessageId(Email $message): ?string
    {
        $header = $message->getHeaders()->get('Message-ID');

        if ($header === null) {
            return null;
        }

        $value = mb_trim($header->getBodyAsString());

        if ($value === '') {
            return null;
        }

        return mb_trim($value, '<>');
    }

    private function extractBody(Email $message): ?string
    {
        $html = $message->getHtmlBody();
        if (is_string($html) && $html !== '') {
            return $html;
        }

        $text = $message->getTextBody();
        if (is_string($text) && $text !== '') {
            return $text;
        }

        return null;
    }

    /**
     * @param  array<int,Address>  $addresses
     */
    private function joinAddresses(array $addresses): string
    {
        return implode(', ', array_map(fn(Address $address) => $address->getAddress(), $addresses));
    }

    /**
     * @param  array<int,Address>  $addresses
     */
    private function firstAddress(array $addresses): ?string
    {
        return isset($addresses[0]) ? $addresses[0]->getAddress() : null;
    }
}
