<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampaignAdClick extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CampaignAdClickId';
    protected $fillable = ['CampaignAddId', 'AdClick', 'CreatedAt', 'UpdatedAt'];

    public function CampaignAdList(){
        return $this->hasOne('App\CampaignAdList', 'CampaignAddId', 'CampaignAddId');
    }    
}