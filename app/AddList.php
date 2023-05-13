<?php
/**
 * Created by PhpStorm.
 * User: A
 * Date: 20-09-2018
 * Time: 13:05
 */

namespace App;

use Illuminate\Database\Eloquent\Model;

class AddList extends Model
{
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = "ad_affiliate_lists";
    protected $primaryKey = 'AdAffiliateId';
    protected $fillable = ['AdId', 'UserId', 'CreatedBy', 'UpdatedBy'];
    protected $hidden = ['CreatedAt','UpdatedAt'];
}