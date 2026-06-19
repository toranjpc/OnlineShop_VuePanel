<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductStock extends Model
{
    use SoftDeletes;

    protected $table = 'product_stock';

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'stock',
        'metadata',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'quantity' => 'integer',
        'stock' => 'integer',
        'status' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'warehouse_id', 'id')->where('kind', 'warehouse');
    }
}
