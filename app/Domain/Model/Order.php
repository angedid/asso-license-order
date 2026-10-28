<?php

namespace App\Domain\Model;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const PENDING = 'PENDING';
    public const PLACED = 'PLACED';
    public const CANCELLED = 'CANCELLED';
    public const COMPLETED = 'COMPLETED';

    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $casts = ['id' => 'string'];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
          'id'
        , 'tenant_id'
        , 'merchant_id'
        , 'license_type_id'
        , 'amount'
        , 'discount'
        , 'final_amount'
        , 'placed_at'
        , 'status'
        , 'paid_at'
        , 'cancelled_at'
        , 'active'
        , 'items'
    ];
}



