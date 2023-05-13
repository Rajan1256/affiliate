<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupportManager extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'SupportId';
    protected $fillable = ['TicketId', 'FromUserId', 'ToUserId','Message','IsRead','CreatedAt','UpdatedAt'];
    protected $hidden = ['UpdatedAt'];

}