<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampaignTypeMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CampaignTypeId';
    protected $fillable = ['Type', 'Description', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}