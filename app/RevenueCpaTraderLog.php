<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaTraderLog extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaTraderLogId';
    protected $fillable = [	'RevenueModelLogId', 'RevenueCpaCountryLogId', 'RangeFrom','RangeExpression','RangeTo'];
    protected $hidden = [ 'CreatedAt', 'UpdatedAt' ];

    public function RevenueCpaPlan(){
        return $this->hasMany('App\RevenueCpaPlan', 'RevenueModelId', 'RevenueModelId');
    }

}