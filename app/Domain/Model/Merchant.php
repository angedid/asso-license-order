<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    protected $table = 'merchants';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'tenant_id'
        , 'type'
        , 'account_id'
        , 'full_name'
        , 'username'
        , 'email'
        , 'phone_numbers'
        , 'active'
    ];
}
