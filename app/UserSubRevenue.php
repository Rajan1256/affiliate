<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserSubRevenue extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserSubRevenueId';
    protected $fillable = ['UserId', 'UserRevenuePaymentId', 'Amount','USDAmount','AUDAmount','EURAmount', 'CurrencyConvertId', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function UserRevenuePayment(){
        return $this->hasOne('App\UserRevenuePayment', 'UserRevenuePaymentId', 'UserRevenuePaymentId');
    }
    
    public function UserSubRevenuePayment(){
        return $this->hasOne('App\UserRevenuePayment', 'UserSubRevenueId', 'UserSubRevenueId');
    }

    public function User(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function RoyalRevenue(){
        return $this->hasOne('App\RoyalRevenue', 'UserRevenuePaymentId', 'UserRevenuePaymentId');
    }

}