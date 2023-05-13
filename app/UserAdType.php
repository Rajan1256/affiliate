<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserAdType extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserAdTypeId';
    protected $fillable = ['UserId', 'AdTypeId', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Type(){
    	return $this->hasOne('App\AdTypeMaster', 'AdTypeId', 'AdTypeId');
    }
}