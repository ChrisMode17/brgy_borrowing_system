<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $primaryKey = 'equipment_id';
    public $timestamps = false;

    protected $fillable = ['name', 'category_id', 'total_quantity', 'source_equipment_id'];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id', 'category_id');
    }

    public function statuses()
    {
        return $this->hasMany(InventoryStatus::class, 'equipment_id', 'equipment_id');
    }

    public function source()
    {
        return $this->belongsTo(Inventory::class, 'source_equipment_id', 'equipment_id');
    }

    public function borrowItems()
    {
        return $this->hasMany(BorrowItem::class, 'equipment_id', 'equipment_id');
    }
}
