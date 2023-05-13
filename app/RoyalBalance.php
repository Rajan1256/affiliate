<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RoyalBalance extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RoyalBalanceId';
    protected $fillable = [
        'USDTotalRevenue', 'AUDTotalRevenue', 'EURTotalRevenue', 
        'CreatedAt', 'UpdatedAt'
    ];

}