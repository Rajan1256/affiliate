<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueModel extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueModelId';

    protected $fillable = [ 
        'RevenueModelName', 'RevenueTypeId', 'CurrencyId', 'AmountFlag', 
        'Amount', 'USDAmount', 'AUDAmount', 'EURAmount', 'CurrencyConvertId', 
        'Percentage', 'Rebate', 'TradeType', 'TradeValue', 'Schedule', 
        'TotalAccTradeVol', 'TotalIntroducedAcc', 'BonusValue', 'Type', 
        'ReferenceDeal', 'IsActive', 'Comment', 
        'CreatedBy', 'UpdatedBy' 
    ];

    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Currency(){
        return $this->hasOne('App\CurrencyMaster', 'CurrencyId', 'CurrencyId');
    }

    public function Revenue(){
        return $this->hasOne('App\RevenueType', 'RevenueTypeId', 'RevenueTypeId');
    }

    public function RevenueCpaPlan(){
        return $this->hasMany('App\RevenueCpaPlan', 'RevenueModelId', 'RevenueModelId');
    }

    public function RevenueCpaCountry(){
        return $this->hasMany('App\RevenueCpaPlan', 'RevenueCpaCountryId', 'RevenueCpaCountryId');
    }

    public function UserRevenueType(){
        return $this->hasOne('App\UserRevenueType', 'RevenueModelId', 'RevenueModelId');
    }

}