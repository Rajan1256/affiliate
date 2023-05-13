<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampaignAdList extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CampaignAddId';
    protected $fillable = ['CampaignId', 'AdId', 'UserId', 'AdClicks', 'Impressions', 'IsDeleted'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Campaign(){
        return $this->hasOne('App\Campaign', 'CampaignId', 'CampaignId');
    }

    public function Campaignmany(){
        return $this->hasMany('App\Campaign', 'CampaignId', 'CampaignId');
    }


    public function Ad(){
        return $this->hasOne('App\Ad', 'AdId', 'AdId');
    }
}