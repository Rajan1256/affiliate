<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserPayment extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserPaymentId';
    protected $fillable = [ 'UserId', 'UserPaymentRequestId', 'InitialBalance',
                            'PaymentAmount', 'RemaingBalance', 'USDAmount', 'CurrencyConvertId',
                            'EURAmount','AUDAmount','DateOfPayment','PaymentTypeId',
                            'CurrencyId','Attachment','Comments',
                            'CreatedBy','UpdatedBy','CreatedAt', 'UpdatedAt'];
   // protected $hidden = ['CreatedAt', 'UpdatedAt'];


    public function userdata(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

    public function paymentrequest(){
        return $this->hasOne('App\UserPaymentRequest', 'UserPaymentRequestId', 'UserPaymentRequestId');
    }

    public function Paytype(){
        return $this->hasOne('App\PaymentType', 'PaymentTypeId', 'PaymentTypeId');
    }

}