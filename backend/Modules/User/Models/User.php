<?php

namespace Modules\User\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Concerns\HasCredits;
use Modules\Product\Models\Credit;

class User extends Authenticatable
{
    /** @use HasFactory<\Modules\User\Database\factories\UserFactory> */
    use HasApiTokens, HasFactory, HasCredits, Notifiable, SoftDeletes;

    protected $fillable = [
        "f_id",
        "sex",
        "ircode",
        "name",
        "lastname",
        "birth",
        // "alias",
        "username",
        "mobile",
        "password",
        "job",
        "per",
        "datas",
        "status",
    ];

    protected $casts = [
        'mobile_verified_at' => 'datetime',
        'birth' => 'date',
        'per' => 'array',
        'datas' => 'array',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];


    public function jobOption()
    {
        return $this->belongsTo(Option::class, 'job', 'id')
            ->where('kind', 'job');
    }

    public function reagent()
    {
        return $this->belongsTo(User::class, 'f_id', 'id')
            ->select('id', 'name', 'lastname');
    }

    public function category()
    {
        return $this->hasOneThrough(
            Option::class,
            ExtData::class,
            'f_id',     // ExtData.f_id -> User.id
            'id',       // Option.id -> ExtData.m_id  
            'id',       // User.id
            'm_id'      // ExtData.m_id -> Option.id
        )->where('extdatas.kind', 'UserCategory')
            ->where('options.kind', 'Category');
    }
    public function userPlan()
    {
        return $this->hasOneThrough(
            Option::class,
            ExtData::class,
            'f_id',     // ExtData.f_id -> User.id
            'id',       // Option.id -> ExtData.m_id  
            'id',       // User.id
            'm_id'      // ExtData.m_id -> Option.id
        )->where('extdatas.kind', 'UserPlan')
            ->where('extdatas.status', 1)
            ->where('options.kind', 'Plan');
    }

    public function userPlans()
    {
        return $this->belongsToMany(Option::class, 'extdatas', 'f_id', 'm_id')
            ->withPivot('datas', 'kind')
            ->wherePivot('kind', 'UserPlan')
            ->where('options.kind', 'Plan');
    }

    public function lastPresence()
    {
        return $this->hasOne(ExtData::class, 'f_id', 'id')
            ->orderByDesc('id')
            ->whereIn('kind', ['login', 'logout', 'online', 'offline'])
            ->where('status', 1);
    }

    public function extraData()
    {
        return $this->hasMany(ExtData::class, 'f_id', 'id')
            ->where('kind', 'Data');
    }

    protected function creditEntityTypes(): array
    {
        return Credit::USER_TYPES;
    }
}
