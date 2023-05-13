<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CountryMaster extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $primaryKey = 'CountryId';
    protected $fillable = [ 'CountryName', 'CountryNameShortCode', 'CountryCode' ];
    protected $hidden = ['CreatedAt', 'UpdatedAt'];
}