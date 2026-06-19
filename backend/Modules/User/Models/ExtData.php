<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtData extends Model
{
    use HasFactory;
    protected $table = 'extdatas';

    protected $fillable = [
        'f_id',
        'm_id',
        's_id',
        'title',
        'kind',
        'datas',
        'status',
        'updated_at'
    ];

    protected $casts = [
        'datas' => 'array',
    ];

    //get user data
    public function uf()
    {
        return $this->belongsTo(User::class, 'f_id', 'id');
    }
    public function um()
    {
        return $this->belongsTo(User::class, 'm_id', 'id');
    }
    public function us()
    {
        return $this->belongsTo(User::class, 's_id', 'id');
    }

    //get option data
    public function of()
    {
        return $this->belongsTo(Option::class, 'f_id', 'id');
    }
    public function om()
    {
        return $this->belongsTo(Option::class, 'm_id', 'id');
    }
    public function os()
    {
        return $this->belongsTo(Option::class, 's_id', 'id');
    }


}
