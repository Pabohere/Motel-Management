<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'name'];
}
