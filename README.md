# Marketing Module

Central email sending and communications log for the noerd platform.

## Purpose

- Single entry point for sending application emails across all modules
- Persistent log of every email in the `communications` table
- Replaces the legacy `mail_logs` table (data migrated automatically)

## Usage

```php
use Noerd\Marketing\Services\Communicator;

app(Communicator::class)->send(
    mailable: new MyMailable($data),
    to: $customer->email,
    customer: $customer,
);
```

## Installation

1. `composer require noerd/marketing`
2. `php artisan migrate`
3. `php artisan noerd:install-marketing`
