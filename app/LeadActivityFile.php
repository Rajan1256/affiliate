<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadActivityFile extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadActivityFileId';
    protected $table = 'lead_activity_files';
    protected $fillable = [	
    						'LeadActivityFileId', 
    						'FileName', 
    						'CreatedBy', 
    						'UpdatedBy',  
                        ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
    
}