<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaPlan extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaPlanId';
    protected $fillable = [ 
        'RevenueModelId', 'RevenueTypeId', 'RevenueCpaCountryId', 'CurrencyId', 
        'Amount', 'USDAmount', 'AUDAmount', 'EURAmount', 'CurrencyConvertId', 
        'RevenueCpaTraderId',  'CreatedBy', 'UpdatedBy' 
    ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueCpaCountry(){
        return $this->hasMany('App\RevenueCpaCountry', 'RevenueCpaCountryId', 'RevenueCpaCountryId');
    }

    public function RevenueCpaRrader(){
        return $this->hasMany('App\RevenueCpaTrader', 'RevenueCpaTraderId', 'RevenueCpaTraderId');
    }

}