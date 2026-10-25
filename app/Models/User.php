<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'staff', 'active' => true];

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'position', 'biography', 'photo_path'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean'];
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
}
