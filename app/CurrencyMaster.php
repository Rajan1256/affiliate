<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class CurrencyMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'CurrencyId';
    protected $fillable = ['CurrencyCode', 'CurrencyName', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}