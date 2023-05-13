<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaTrader extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaTraderId';
    protected $fillable = ['RevenueModelId', 'RevenueCpaCountryId', 'RangeFrom','RangeExpression','RangeTo'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueCpaPlan(){
        return $this->hasMany('App\RevenueCpaPlan', 'RevenueModelId', 'RevenueModelId');
    }

}