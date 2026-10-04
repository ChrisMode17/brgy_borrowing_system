<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackgroundImage extends Model
{
    public $timestamps = false;
    protected $fillable = ['file_path', 'original_name', 'uploaded_by', 'created_at'];
}
