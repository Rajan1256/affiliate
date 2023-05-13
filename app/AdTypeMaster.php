<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdTypeMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'AdsTypeId';
    protected $fillable = ['Title', 'IsActive', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}