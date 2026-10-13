<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, Model $subject, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(), 'before' => $before, 'after' => $after, 'created_at' => now()]);
    }
}
