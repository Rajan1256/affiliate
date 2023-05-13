<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Priority extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected  $table = 'priority_master';
    protected $primaryKey = 'PriorityId';
    protected $fillable = ['PriorityName', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}