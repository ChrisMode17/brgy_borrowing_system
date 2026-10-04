<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $primaryKey = 'refnum';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['refnum', 'borrower_id', 'dateBorrowed', 'returnDate', 'status'];

    protected function casts(): array
    {
        return [
            'dateBorrowed' => 'datetime',
            'returnDate' => 'datetime',
        ];
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class, 'borrower_id', 'borrower_id');
    }

    public function items()
    {
        return $this->hasMany(BorrowItem::class, 'refnum', 'refnum');
    }
}
