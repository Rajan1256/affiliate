<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadInformationFile extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadInformationFileId';
    protected $table = 'lead_information_files';
    protected $fillable = [	
    						'LeadInformationFileId', 
    						'FileName', 
    						'CreatedBy', 
    						'UpdatedBy',  
                        ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}