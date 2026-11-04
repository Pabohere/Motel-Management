<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            $clean = function (?array $values) use (&$clean): ?array {
                if ($values === null) {
                    return null;
                }
                foreach ($values as $key => $value) {
                    if (preg_match('/password|token|secret|file_contents/i', (string) $key)) {
                        unset($values[$key]);
                    } elseif (is_array($value)) {
                        $values[$key] = $clean($value);
                    }
                }

                return $values;
            };
            $log->before = $clean($log->before);
            $log->after = $clean($log->after);
        });
        static::updating(fn () => throw new \LogicException('Audit records are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit records are append-only.'));
    }

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
