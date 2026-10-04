<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    protected $primaryKey = 'category_id';
    public $timestamps = false;
    protected $fillable = ['category_name'];

    public function items()
    {
        return $this->hasMany(Inventory::class, 'category_id', 'category_id');
    }
}
