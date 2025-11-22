<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\VideoFile;
use App\Models\User;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\VideoView;



class Video extends Model
{
    //
    protected $fillable = ['title','description','created_by',
    'status','duration','thumbnail','category_id','subcategory_id','season_id','is_trending','year_of_published'];

    public function files(): HasMany
{
return $this->hasMany(VideoFile::class);
}


public function uploader()
{
return $this->belongsTo(User::class, 'created_by');
}

public function category()
{
    return $this->belongsTo(Category::class);
}
public function views()
{
    return $this->hasMany(VideoView::class);
}
public function subcategory()
{
    return $this->belongsTo(Subcategory::class);
}
public function season()
{
    return $this->belongsTo(Season::class);
}
}
