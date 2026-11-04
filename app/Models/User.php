<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'staff', 'active' => true, 'can_view_documents' => true, 'can_upload_documents' => true];

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'position', 'biography', 'photo_path', 'can_view_documents', 'can_upload_documents'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'active' => 'boolean', 'can_view_documents' => 'boolean', 'can_upload_documents' => 'boolean'];
    }

    public function canViewDocuments(): bool
    {
        return $this->active && ($this->isManager() || $this->can_view_documents);
    }

    public function canUploadDocuments(): bool
    {
        return $this->active && ($this->isManager() || $this->can_upload_documents);
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
}
