<?php

namespace Noerd\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Customer\Models\Customer;
use Noerd\Marketing\Database\Factories\CommunicationFactory;
use Noerd\Marketing\Enums\CommunicationStatus;
use Noerd\Marketing\Enums\CommunicationType;
use Noerd\Traits\BelongsToTenant;

class Communication extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected static function newFactory(): CommunicationFactory
    {
        return CommunicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => CommunicationType::class,
            'status' => CommunicationStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
