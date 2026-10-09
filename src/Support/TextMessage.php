<?php

namespace Noerd\Communication\Support;

/**
 * A provider-neutral text message.
 *
 * `body` is always the plain text — it is logged and sent wherever no template applies.
 * `template` is a stable key of the sending module (e.g. `liefertool.order_placed`) that a
 * driver may map to a pre-approved provider template, filled with `variables`.
 */
final readonly class TextMessage
{
    /**
     * @param  string  $to  E.164 phone number (`+4917112345678`)
     * @param  array<string,string>  $variables
     */
    public function __construct(
        public string $to,
        public string $body,
        public ?string $template = null,
        public array $variables = [],
        public ?int $tenantId = null,
    ) {}
}
