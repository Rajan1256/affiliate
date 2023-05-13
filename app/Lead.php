<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadId';

    protected $fillable = [
    						'RefId', 'AdId', 'UserId', 'CampaignId', 'FirstName', 'LastName', 
                            'Email', 'CountryFromInput','CountryFromIp','ContryFromSheet', 
                            'PhoneNumber', 'LeadStatus', 'IsActive', 'IsConverted', 
                            'RegistrationDate', 'DateConverted', 'LeadIPAddress', 'AccountId', 
                            'SFAccountID'
    					];
    					
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Ad(){
        return $this->hasOne('App\Ad', 'AdId', 'AdId');
    }

    public function Campaign(){
        return $this->hasOne('App\Campaign', 'CampaignId', 'CampaignId');
    }

    public function LeadInformation(){
        return $this->hasMany('App\LeadInformation', 'LeadId', 'RefId')->orderBy('LeadInformationId', 'desc');
    }

    public function LeadActivity(){
        return $this->hasMany('App\LeadActivity', 'AccountId', 'AccountId');
    }

    public function LeadStatus(){
    	return $this->hasMany('App\LeadStatusMaster', 'Status', 'LeadStatus');
    }

    public function Affiliate(){
    	return $this->hasOne('App\User', 'UserId', 'UserId');
    }

}