<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LanguageMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'LanguageId';
    protected $fillable = ['LanguageName', 'LanguageCode', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}