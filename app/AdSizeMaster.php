<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdSizeMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'AdsSizeId';
    protected $fillable = [
    	'Width', 'Height', 'IsActive', 'CreatedBy', 'UpdatedBy'
    ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}