<?php

namespace App;


use Illuminate\Database\Eloquent\Model;
use Storage;
class News extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const FEEDS_FOLDER = 'news';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $table = 'news';
    protected $primaryKey = 'NewsId';
    protected $fillable = [ 
        'Title', 'NewsImage', 'Description', 'IsPublic',  'IsActive', 
        'CreatedBy', 'UpdatedBy'
    ];

    public function NewsList(){
        return $this->hasMany('App\NewsList', 'NewsId', 'NewsId');
    }

    public function NewsBrandList(){
        return $this->hasMany('App\NewsBrandList', 'NewsId', 'NewsId');
    }

}
