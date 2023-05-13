<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserRevenueType extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserAdTypeId';
    protected $fillable = ['UserId', 'RevenueTypeId', 'RevenueModelId', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function RevenueModel(){
    	return $this->hasOne('App\RevenueModel', 'RevenueModelId', 'RevenueModelId');
    }

    public function RevenueType(){
    	return $this->hasOne('App\RevenueType', 'RevenueTypeId', 'RevenueTypeId');
    }
    
    public function User(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

}