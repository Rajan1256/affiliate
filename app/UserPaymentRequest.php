<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserPaymentRequest extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserPaymentRequestId';
    protected $fillable = [ 'UserId', 'PaymentTypeId', 'RequestAmount', 'RemainingBalance', 'CurrencyId', 'CurrencyConvertId', 'TimeZone' ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];


    public function userpayment(){
        return $this->hasOne('App\UserPayment', 'UserPaymentRequestId', 'UserPaymentRequestId');
    }

    public function userrequest(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function Pay(){
        return $this->hasOne('App\PaymentType', 'PaymentTypeId', 'PaymentTypeId');
    }

}