<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Models\User;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'category_id',
        'title',
        'slug',
        'description',
        'user_id',
        'tax',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'tax' => 'integer',
        'status' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function category(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'category_id', 'id')->where('kind','category');
    }


}
