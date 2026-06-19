<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Concerns\HasCredits;
use Modules\Product\Models\Credit;
use Modules\User\Models\Option;

class ProductOption extends Model
{
    use HasCredits, SoftDeletes;

    protected $table = 'product_options';

    protected $fillable = [
        'option_id',
        'title',
        'metadata',
        'kind',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'option_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'option_id');
    }

    protected function creditEntityTypes(): array
    {
        return [Credit::TYPE_BANK];
    }

    public function scopeBanks($query)
    {
        return $query->where('kind', Credit::TYPE_BANK);
    }
}
