<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'TicketId';
    protected $fillable = ['UserId', 'TicketTitle', 'TicketSubject','TicketTypeId','PriorityId','TicketDescription','TicketStatus','CreatedAt','UpdatedAt'];
    //protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function tickettype(){
        return $this->hasOne('App\TicketType', 'TicketTypeId', 'TicketTypeId');
    }

    public function priortytype(){
        return $this->hasOne('App\Priority', 'PriorityId', 'PriorityId');
    }

    public function userticket(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }
}