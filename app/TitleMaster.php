<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TitleMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'TitleId';
    protected $fillable = ['Title', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}