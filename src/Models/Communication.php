<?php

namespace Noerd\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Noerd\Communication\Database\Factories\CommunicationFactory;
use Noerd\Communication\Enums\CommunicationDirection;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Traits\BelongsToTenant;

class Communication extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The source record this mail was generated from (an order, a membership, ...).
     */
    public function model(): MorphTo
    {
        return $this->morphTo('model');
    }

    /**
     * The record this mail concerns (a party, a member, ...). Independent of model():
     * an order confirmation links the order as model and the ordering party as contact.
     */
    public function contact(): MorphTo
    {
        return $this->morphTo('contact');
    }

    /**
     * The outbound message an inbound one answers.
     */
    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === CommunicationDirection::Inbound;
    }

    protected static function newFactory(): CommunicationFactory
    {
        return CommunicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => CommunicationType::class,
            'direction' => CommunicationDirection::class,
            'status' => CommunicationStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
