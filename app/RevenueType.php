<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RevenueType extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'RevenueTypeId';
    protected $fillable = [ 'RevenueTypeName', 'RevenueTypeDescription', 'IsActive' ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function UserRevenueType(){
    	$this->hasMany('App\UserRevenueType', 'RevenueTypeId', 'RevenueTypeId');
    }
}