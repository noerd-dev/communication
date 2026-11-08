# Communication Module

Central email and text message (SMS, WhatsApp) sending and communications log for the noerd platform.

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

## Text messages (SMS, WhatsApp)

```php
app(Communicator::class)->sendText(
    type: CommunicationType::WhatsApp,
    to: $settings->whatsapp_number,      // any notation, or a model with a `phone` attribute
    body: __('New order from :name', ['name' => $customer->name]),
    template: 'liefertool.order_placed', // a driver may map it to an approved provider template
    variables: ['1' => $customer->name],
    model: $order,
    queue: true,
);
```

The module ships no provider: a separate provider module registers a
`TextMessageDriver` per channel on the `TextMessageChannelRegistry`. Without one, `sendText()`
returns `null`. Every accepted message is recorded in `communication_usages` for invoicing; the
retention command never deletes these rows.

### Delivery status

A provider module reports what happened to a sent message through
`Communicator::updateTextStatus($messageId, CommunicationStatus::Delivered|Read|Failed, $reason)`.
A report never moves a message back, so out-of-order callbacks are harmless.

### Replies

A provider module's webhook passes every incoming message to `receiveText()`:

```php
app(Communicator::class)->receiveText(
    type: CommunicationType::Sms,
    from: $request->input('From'),
    to: $request->input('To'),
    body: (string) $request->input('Body'),
    messageId: $request->input('MessageSid'),
    provider: 'twilio',
);
```

The reply is logged as an `inbound` row in the tenant of the latest outbound message to the
sender's number, with that message's `contact` and `model` and `in_reply_to_id` pointing at it;
then `Events\TextMessageReceived` fires inside that tenant's context. A sender nobody wrote to is
logged without a tenant. A message id that was logged already is not logged again.

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
