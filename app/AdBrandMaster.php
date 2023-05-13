<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdBrandMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'AdBrandId';
    protected $fillable = [ 'Title', 'IsActive', 'CreatedBy', 'UpdatedBy' ];
    protected $hidden = [ 'CreatedAt', 'UpdatedAt' ];

    public function NewsBrandList(){
        return $this->hasOne('App\NewsBrandList', 'AdBrandId', 'AdBrandId');
    }
}