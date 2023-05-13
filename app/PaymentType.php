<?php 

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';
    
    protected $primaryKey = 'PaymentTypeId';
    protected $fillable = ['PaymentTypeName', 'PaymentTypeDescription', 'IsActive'];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}