<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'TicketTypeId';
    protected $fillable = ['TicketTypeTitle', 'TicketTypeDescription', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}