<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserAdBrand extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'UserAdBrandId';
    protected $fillable = ['UserId', 'AdBrandId', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Brand(){
    	return $this->hasOne('App\AdBrandMaster', 'AdBrandId', 'AdBrandId');
    }

}