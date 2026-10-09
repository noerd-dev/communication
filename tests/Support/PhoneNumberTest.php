<?php

declare(strict_types=1);

use Noerd\Communication\Support\PhoneNumber;

uses(Tests\TestCase::class);

it('normalises phone numbers to E.164', function (?string $raw, ?string $expected): void {
    expect(PhoneNumber::normalize($raw))->toBe($expected);
})->with([
    'international' => ['+49 171 1234567', '+491711234567'],
    'double zero' => ['0049 (171) 123-4567', '+491711234567'],
    'national' => ['0171/1234567', '+491711234567'],
    'without prefix' => ['491711234567', '+491711234567'],
    'empty' => ['', null],
    'null' => [null, null],
    'letters' => ['abc', null],
    'too short' => ['+4912', null],
]);
