<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadStatusMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadStatusId';
    protected $table = 'lead_status_masters';
    protected $fillable = [	
    						'Status', 
    						'IsValid', 
                        ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}