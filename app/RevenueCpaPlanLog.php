<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueCpaPlanLog extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueCpaPlanLogId';
    protected $fillable = [ 
        'RevenueModelLogId', 'RevenueCpaCountryLogId', 'RevenueCpaTraderLogId',
        'Amount', 'USDAmount', 'AUDAmount', 'EURAmount', 'CurrencyConvertId'
    ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueCpaCountry(){
        return $this->hasMany('App\RevenueCpaCountry', 'RevenueCpaCountryId', 'RevenueCpaCountryId');
    }

    public function RevenueCpaRrader(){
        return $this->hasMany('App\RevenueCpaTrader', 'RevenueCpaTraderId', 'RevenueCpaTraderId');
    }

}