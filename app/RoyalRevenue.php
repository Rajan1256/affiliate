<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RoyalRevenue extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RoyalRevenueId';
    protected $fillable = [ 
    						'UserRevenuePaymentId', 'UserId', 'LeadId', 
                            'RevenueModelLogId',  'LeadActivityId', 
                            'USDAmount', 'AUDAmount', 'EURAmount', 
                            'USDSpreadAmount', 'AUDSpreadAmount', 'EURSpreadAmount', 
                            'CurrencyConvertId', 'ActualRevenueDate', 
                            'CreatedAt', 'UpdatedAt' 
                        ];

	/*public function LeadDetail(){
        return $this->hasOne('App\Lead', 'LeadId', 'LeadId');
    }*/

    public function LeadActivity(){
        return $this->hasOne('App\LeadActivity', 'LeadActivityId', 'LeadActivityId');
    }
    
    public function User(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

}