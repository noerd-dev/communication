<?php

namespace Noerd\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Noerd\Communication\Database\Factories\CommunicationUsageFactory;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Traits\BelongsToTenant;

/**
 * A billable message a provider accepted. Never pruned — it is the basis for invoicing.
 */
class CommunicationUsage extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class);
    }

    protected static function newFactory(): CommunicationUsageFactory
    {
        return CommunicationUsageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => CommunicationType::class,
            'units' => 'integer',
            'cost' => 'decimal:5',
            'occurred_at' => 'datetime',
        ];
    }
}
