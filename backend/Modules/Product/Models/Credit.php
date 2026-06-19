<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\User\Models\Customer;
use Modules\User\Models\User;

class Credit extends Model
{
    use SoftDeletes;

    public const TYPE_BANK = 'bank';
    public const TYPE_USER = 'user';
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_ACCOUNT = 'account';

    public const USER_TYPES = [self::TYPE_USER, self::TYPE_ACCOUNT];

    protected $fillable = [
        'user_id',
        'invoice_id',
        'receive_type',
        'receive_id',
        'payment_type',
        'payment_id',
        'amount',
        'pay_date',
        'pay_trace',
        'description',
        'status',
        'metadata',
    ];

    protected $casts = [
        'pay_date' => 'datetime',
        'amount' => 'integer',
        'status' => 'integer',
        'metadata' => 'array',
    ];

    public function scopeForParty(Builder $query, array $types, int $partyId): Builder
    {
        return $query->where(function (Builder $query) use ($types, $partyId) {
            $query->where(function (Builder $query) use ($types, $partyId) {
                $query->whereIn('receive_type', $types)
                    ->where('receive_id', $partyId);
            })->orWhere(function (Builder $query) use ($types, $partyId) {
                $query->whereIn('payment_type', $types)
                    ->where('payment_id', $partyId);
            });
        });
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): User|Customer|ProductOption|null
    {
        return $this->resolveParty($this->receive_type, $this->receive_id);
    }

    public function payer(): User|Customer|ProductOption|null
    {
        return $this->resolveParty($this->payment_type, $this->payment_id);
    }

    protected function resolveParty(?string $type, ?int $id): User|Customer|ProductOption|null
    {
        if (!$type || !$id) {
            return null;
        }

        return match ($type) {
            self::TYPE_BANK => ProductOption::query()->where('kind', self::TYPE_BANK)->find($id),
            self::TYPE_CUSTOMER => Customer::query()->find($id),
            self::TYPE_USER, self::TYPE_ACCOUNT => User::query()->find($id),
            default => null,
        };
    }
}
