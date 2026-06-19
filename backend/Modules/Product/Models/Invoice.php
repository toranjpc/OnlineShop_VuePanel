<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Models\Customer;
use Modules\User\Models\User;

class Invoice extends Model
{
    use SoftDeletes;

    public const KIND_SALE = 'sale';
    public const KIND_PURCHASE = 'purchase';
    public const KIND_PERFORMA = 'performa';
    public const KIND_RETURN = 'return';
    public const KIND_TRANSFERENCE = 'transference';

    protected $fillable = [
        'invoice_number',
        'invoice_id',
        'customer_id',
        'user_id',
        'product_id',
        'pay_date',
        'pay_trace',
        'amount',
        'total',
        'tax',
        'kind',
        'status',
        'metadata',
    ];

    protected $casts = [
        'pay_date' => 'datetime',
        'amount' => 'integer',
        'total' => 'integer',
        'tax' => 'integer',
        'status' => 'integer',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'id');
    }
}
