<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadInformation extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadInformationId';
    protected  $table = 'lead_informations';
    protected $fillable = [ 
    						'LeadId', 
    						'LeadFileInfo', 
    						'LeadStatus', 
    						'Country', 
    						'IsConverted', 
    						'IsActive', 
    						'DateConverted', 
    						'AccountId', 
    						'SFAccountID', 
    						'CreatedAt', 
    						'UpdatedAt' 
    					];

    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Leads(){
        return $this->hasOne('App\Lead', 'AccountId', 'AccountId');
    }

    public function LeadData(){
        return $this->hasOne('App\Lead', 'RefId', 'LeadId');
    }

    public function LeadFile(){
        return $this->hasOne('App\LeadInfoFile', 'LeadFileInfo', 'LeadFileInfo');
    }

}