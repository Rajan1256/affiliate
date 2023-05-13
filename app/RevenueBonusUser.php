<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueBonusUser extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueBonusUserId';
    protected $fillable = [ 'RevenueModelId', 'UserId' ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Users(){
    	return $this->hasOne('App\User', 'UserId', 'UserId');
    }

}