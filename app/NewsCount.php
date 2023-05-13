<?php

namespace App;


use Illuminate\Database\Eloquent\Model;


class NewsCount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = 'news_counts';
    protected $primaryKey = 'NewsCountId';
    protected $fillable = [
        'NewsId', 'UserId', 'CreatedBy', 'UpdatedBy'
    ];

}
