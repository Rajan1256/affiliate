<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaCountryLog extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaCountryLogId';
    protected $fillable = ['RevenueModelLogId', 'RevenueCountrys'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueCpaTraderLog(){
        return $this->hasMany('App\RevenueCpaTraderLog', 'RevenueModelLogId', 'RevenueModelLogId');
    }

}