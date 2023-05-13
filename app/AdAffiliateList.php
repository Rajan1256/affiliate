<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Storage;

class AdAffiliateList extends Model
{ 
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $table = 'ad_affiliate_lists';
    protected $primaryKey = 'AdAffiliateId';
    protected $fillable = [
        'AdId', 'UserId', 'CreatedBy', 'UpdatedBy'
    ];

    public function Ads(){
        return $this->hasMany('App\Ad', 'AdId', 'AdId');
    }

    public function Users(){
        return $this->hasMany('App\User', 'UserId', 'UserId');
    }

    public function UserSingle(){
        return $this->hasOne('App\User', 'UserId', 'UserId');
    }

}
