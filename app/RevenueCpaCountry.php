<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaCountry extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaCountryId';
    protected $fillable = ['RevenueModelId', 'RevenueCountrys'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueCpaTrader(){
        return $this->hasMany('App\RevenueCpaTrader', 'RevenueCpaCountryId', 'RevenueCpaCountryId');
    }

}