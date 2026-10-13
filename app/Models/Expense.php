<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'integer'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
