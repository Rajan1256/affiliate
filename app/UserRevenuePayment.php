<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserRevenuePayment extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserRevenuePaymentId';
    protected $fillable = [ 
		'UserId', 'LeadId', 'RevenueModelLogId', 'UserBonusId', 
        'UserSubRevenueId', 'LeadInformationId', 'LeadActivityId', 
        'Amount', 'AUDAmount', 'EURAmount', 'USDAmount', 
        'SpreadAmount', 'SpreadAUDAmount', 'SpreadEURAmount', 
        'SpreadUSDAmount', 'RoyalCommission', 'RoyalSpread', 'CurrencyConvertId',
        'PaymentStatus', 'ActualRevenueDate', 'CreatedAt', 'UpdatedAt'
    ];

    public function Affiliate(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

	public function LeadDetail(){
        return $this->hasOne('App\Lead', 'LeadId', 'LeadId');
    }
    
    public function LeadActivity(){
        return $this->hasOne('App\LeadActivity', 'LeadActivityId', 'LeadActivityId');
    }

    public function LeadActivitys(){
        return $this->hasMany('App\LeadActivity', 'LeadActivityId', 'LeadActivityId');
    }

    public function LeadInformation(){
        return $this->hasOne('App\LeadInformation', 'LeadInformationId', 'LeadInformationId');
    }

    public function UserSubRevenue(){
        return $this->hasOne('App\UserSubRevenue', 'UserSubRevenueId', 'UserSubRevenueId');
    }
    
    public function UserBonus(){
        return $this->hasOne('App\UserBonus', 'UserBonusId', 'UserBonusId');
    }

    public function RevenueModelLog(){
        return $this->hasOne('App\RevenueModelLog', 'RevenueModelLogId', 'RevenueModelLogId');
    }
    
    public function CurrencyConvert(){
        return $this->hasOne('App\CurrencyConvert', 'CurrencyConvertId', 'CurrencyConvertId');
    }

}