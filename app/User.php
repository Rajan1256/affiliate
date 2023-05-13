<?php

namespace App;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt'; 

    protected $table =  'users';
    protected $primaryKey = 'UserId';
    protected $fillable = [
        'FirstName', 'LastName', 'EmailId', 'Password', 'RoleId', 'Title', 'Phone', 'PhoneCountryId', 'CurrencyId', 'CountryId', 'Address', 'City', 'State', 'PostalCode', 'CompanyName', 'Token', 'ParentId', 'TrackingId', 'EmailVerified', 'AdminVerified', 'IsAllowSubAffiliate', 'IsDeleted', 'Comment', 'LastLogin', 'CreatedBy', 'UpdatedBy'
    ];
    protected $hidden = ['Password', 'CreatedAt', 'UpdatedAt'];

    public function Currency(){
        return $this->hasOne('App\CurrencyMaster', 'CurrencyId', 'CurrencyId');
    }

    public function Country(){
        return $this->hasOne('App\CountryMaster', 'CountryId', 'CountryId');
    }

    public function PhoneCountry(){
        return $this->hasOne('App\CountryMaster', 'CountryId', 'PhoneCountryId');
    }

    public function Title(){
        return $this->hasOne('App\TitleMaster', 'TitleId', 'Title');
    }

    public function UserTokens(){
        return $this->hasMany('App\UserToken', 'UserId', 'UserId');
    }

    public function AdAffiliate(){
        return $this->hasOne('App\AdAffiliateList', 'UserId', 'UserId');
    }
    
    public function NewsAffiliate(){
        return $this->hasOne('App\NewsList', 'UserId', 'UserId');
    }

    public function UserBalance(){
        return $this->hasOne('App\UserBalance', 'UserId', 'UserId');
    }

    public function BankDetail(){
        return $this->hasOne('App\UserBankDetail', 'UserId', 'UserId');
    }

    public function UserRevenueType(){
        return $this->hasOne('App\UserRevenueType', 'UserId', 'UserId')->where('RevenueTypeId', 7);
    }
    
    public function children()
    {
       return $this->hasMany('App\User', 'ParentId', 'UserId');
    }

    // recursive, loads all descendants
    public function SubAffiliate()
    {
       return $this->children()->with('SubAffiliate'); 
    }

}
