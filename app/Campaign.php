<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CampaignId';
    protected $fillable = ['UserId', 'CampaignName', 'CampaignTypeId', 'RevenueModelId', 'IsDeleted'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function CampaignType(){
        return $this->hasOne('App\CampaignTypeMaster', 'CampaignTypeId', 'CampaignTypeId');
    }
    
    public function CommissionType(){
        return $this->hasOne('App\RevenueModel', 'RevenueModelId', 'RevenueModelId');
    } 

    public function User(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function CampaignAdList(){
        return $this->hasMany('App\CampaignAdList', 'CampaignId', 'CampaignId');
    } 
}