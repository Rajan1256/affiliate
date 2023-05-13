<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LeadInfoFile extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'LeadFileInfo';
    protected $table = 'leads_file_infos';

    protected $fillable = [
    						'FileName', 'LeadFileFlage', 'Message', 'CreatedBy', 'UpdatedBy'
                        ];

    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}