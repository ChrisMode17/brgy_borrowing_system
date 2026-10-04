<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Borrower extends Model
{
    protected $primaryKey = 'borrower_id';
    public $timestamps = false;
    protected $fillable = ['first_name', 'last_name', 'address', 'contact'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'borrower_id', 'borrower_id');
    }
}
