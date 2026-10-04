<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStatus extends Model
{
    protected $table = 'inventory_status';
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = ['equipment_id', 'status', 'quantity'];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'equipment_id', 'equipment_id');
    }
}
