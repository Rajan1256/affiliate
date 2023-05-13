<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'ErrorLogId';
    protected $fillable = [ 'LogError', 'Request' ];
    protected $hidden = [ 'CreatedAt', 'UpdatedAt' ];
}