<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueModelLog extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueModelLogId';

    protected $fillable = [ 
			'RevenueModelId', 'RevenueModelName', 'RevenueTypeId', 'CurrencyId', 
			'Amount', 'USDAmount', 'AUDAmount', 'EURAmount', 'CurrencyConvertId', 
			'Percentage', 'Rebate', 'TradeType', 'TradeValue', 'Schedule', 
			'TotalAccTradeVol', 'TotalIntroducedAcc', 'Type', 'ReferenceDeal', 
			'IsActive', 'Comment', 'CreatedBy', 'UpdatedBy' 
		];

    protected $hidden = ['CreatedAt', 'UpdatedAt'];


    public function RevenueModel(){
        return $this->hasOne('App\RevenueModel', 'RevenueModelId', 'RevenueModelId');
    }

}