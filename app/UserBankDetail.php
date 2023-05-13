<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserBankDetail extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserBankDetailId';
    protected $fillable = [ 'UserId', 'PaymentTypeId', 'BankName', 'AccountBeneficiary', 'AccountNumber', 'BankBranch', 'CountryId',  'BankCity', 'SwiftCode', 'IBANNumber', 'BSB', 'SortCode', 'AccountCurrency', 'ABANumber', 'BankCorrespondent', 'VATNumber', 'MT4LoginNumber', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function payment(){
        return $this->hasOne('App\PaymentType', 'PaymentTypeId', 'PaymentTypeId');
    }
}