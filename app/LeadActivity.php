<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadActivityId';
    protected $table='lead_activitys';
    protected $fillable = [	
                            'AccountId', 
    						'LeadsActivityDate', 
    						'LeadFileInfo', 
    						'PlatformLogin', 
    						'BaseCurrency', 
    						'VolumeTraded', 
    						'NumberOfTransactions', 
                            'DepositsBaseCurrency', 
                            'DepositsUSD', 
                            'DepositsAffCur', 
                            'RoyalCommissionBaseCur', 
                            'RoyalCommissionUSD', 
                            'RoyalCommissionAffCur', 
                            'RoyalSpreadBaseCur', 
                            'RoyalSpreadUSD', 
                            'RoyalSpreadAffCur', 
                            'AffCommissionBaseCur', 
                            'AffCommissionUSD', 
                            'AffCommissionAffCur', 
                            'AffSpreadBaseCur', 
                            'AffSpreadUSD', 
                            'AffSpreadAffCur',
                            'ProcessStatus',
                        ];

    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Leads(){
        return $this->hasOne('App\Lead', 'AccountId', 'AccountId');
    }
    
    public function LeadInformation(){
        return $this->hasOne('App\LeadInformation', 'AccountId', 'AccountId');
    }
    
    public function LeadFile(){
        return $this->hasOne('App\LeadInfoFile', 'LeadFileInfo', 'LeadFileInfo');
    }


}