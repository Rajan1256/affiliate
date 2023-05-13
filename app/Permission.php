<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'PermissionId';
    protected $fillable = ['UserId', 'MenuId', 'AccessType', 'MenuName'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Menu(){
        return $this->hasOne('App\Menu', 'MenuId', 'MenuId');
    }

}