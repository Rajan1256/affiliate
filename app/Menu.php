<?php
/**
 * Created by PhpStorm.
 * User: A
 * Date: 20-09-2018
 * Time: 13:05
 */

namespace App;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'MenuId';
    protected $fillable = ['MenuName', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];

    public function Permissions(){
        return $this->hasOne('App\Permission', 'MenuId', 'MenuId');
    }

}