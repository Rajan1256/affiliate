<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class RoleMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'RoleId';
    protected $fillable = ['RoleName', 'RoleDescription', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}