<?php

namespace Noerd\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Communication\Database\Factories\CommunicationFactory;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Customer\Models\Customer;
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
