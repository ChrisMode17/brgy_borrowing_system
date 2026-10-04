<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['refnum', 'equipment_id', 'quantity'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'refnum', 'refnum');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'equipment_id', 'equipment_id');
    }

    public function returnIssues()
    {
        return $this->hasMany(ReturnIssue::class, 'borrow_item_id');
    }
}
