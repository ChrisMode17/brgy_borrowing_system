<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnIssue extends Model
{
    public $timestamps = false;
    protected $fillable = ['borrow_item_id', 'normal_qty', 'broken_qty', 'maintenance_qty', 'issue_status'];
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function borrowItem()
    {
        return $this->belongsTo(BorrowItem::class, 'borrow_item_id');
    }
}
