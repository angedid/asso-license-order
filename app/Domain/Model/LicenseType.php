<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class LicenseType extends Model
{
    public const MONTHLY = 'MONTHLY';
    public const BIYEARLY = 'BIYEARLY';
    public const YEARLY = 'YEARLY';
    public const TRIAL = 'TRIAL';

    protected $table = 'license_types';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'name'
        , 'number_of_days'
        , 'price'
        , 'active'
    ];
}
