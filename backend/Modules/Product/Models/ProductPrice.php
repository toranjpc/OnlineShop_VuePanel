<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Models\Option;
use Modules\User\Models\User;

class ProductPrice extends Model
{
    use SoftDeletes;

    protected $table = 'product_prices';

    protected $fillable = [
        'product_id',
        // 'user_id',
        'user_category_id',
        'price',
        'limit_sale',
    ];

    protected $casts = [
        'price' => 'integer',
        'limit_sale' => 'integer',
    ];


    // public function user(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id', 'id');
    // }

    public function userCategory(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'user_category_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
