<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Concerns\HasCredits;
use Modules\Product\Models\Credit;

class Customer extends Model
{
    use HasCredits, SoftDeletes;

    protected $fillable = [
        'f_id',
        'shenase_meli',
        'name',
        'last_name',
        'registrationDate',
        'registrationTypeTitle',
        'lastCompanyNewsDate',
        'NewsDateFrom',
        'shomare_sabt',
        'code_eghtesadi',
        'postal_code',
        'phone',
        'mobile',
        'webSite',
        'email',
        'address',
        'province',
        'city',
        'metadata',
        'status',
    ];

    protected $casts = [
        'registrationDate' => 'date',
        'lastCompanyNewsDate' => 'date',
        'NewsDateFrom' => 'date',
        'metadata' => 'array',
    ];

    protected $appends = ['title'];

    public function getTitleAttribute(): ?string
    {
        $parts = array_filter([$this->name, $this->last_name]);

        return $parts ? implode(' ', $parts) : null;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'f_id', 'id')
            ->select('id', 'name', 'lastname', 'mobile', 'username');
    }

    public function category()
    {
        return $this->hasOneThrough(
            Option::class,
            ExtData::class,
            'f_id',
            'id',
            'id',
            'm_id'
        )->where('extdatas.kind', 'CustomerCategory')
            ->where('options.kind', 'Category');
    }

    public function reagentExtData()
    {
        return $this->hasOne(ExtData::class, 'm_id', 'id')
            ->where('kind', 'Reagent');
    }

    protected function creditEntityTypes(): array
    {
        return [Credit::TYPE_CUSTOMER];
    }
}
