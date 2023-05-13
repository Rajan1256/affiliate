<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampaignAdImpression extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CampaignAdImpressionId';
    protected $fillable = ['CampaignAddId', 'AdImpression', 'CreatedAt', 'UpdatedAt'];
    
    public function CampaignAdList(){
        return $this->hasOne('App\CampaignAdList', 'CampaignAddId', 'CampaignAddId');
    }    
}