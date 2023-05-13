<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserBalance extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserBalanceId';
    protected $fillable = ['UserId', 'TotalRevenue', 'Paid', 'OutstandingRevenue','TotalDuepayment'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];


    public function user(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function paymentmethod(){
        return $this->hasOne('App\UserBankDetail', 'UserId', 'UserId');
    }

}