# Communication Module

Central email sending and communications log for the noerd platform.

## Purpose

- Single entry point for sending application emails across all modules
- Persistent log of every email in the `communications` table
- Replaces the legacy `mail_logs` table (data migrated automatically)

## Usage

```php
use Noerd\Communication\Services\Communicator;

app(Communicator::class)->send(
    mailable: new OrderConfirmationMail($order),
    to: $order->email,
    contact: $order->party,   // the record the mail concerns
    model: $order,            // the record it was generated from
);
```

`to:` accepts an email, a list of emails, or any Eloquent model carrying an `email`
attribute.

## Two independent record links

Every communication can reference two records polymorphically, and they are independent of
each other:

| Columns | Relation | Meaning |
|---|---|---|
| `model_type` / `model_id` | `model()` | The source record the mail was generated from |
| `contact_type` / `contact_id` | `contact()` | The record the mail concerns |

An order confirmation therefore links the order as `model` and the ordering party as
`contact`. When `to:` is itself a model and no `contact:` is given, that model becomes the
contact. The tenant is derived from the contact's `tenant_id` unless `tenantSettings`
supplies one.

The module depends on no domain implementation: both links accept any Eloquent model, and
neither column carries a foreign key.

## Installation

```bash
composer require noerd/communication
php artisan noerd:install-communication
```

`noerd:install-communication` copies the YAML configs into `app-configs/communication/`, registers
the tenant app and runs the module migrations (confirmation prompt).

Run `php artisan noerd:update-communication` after upgrading the package (idempotent, also covered
by `noerd:update-all`).
