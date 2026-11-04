<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SubmissionKey
{
    public static function fingerprint(array $data, array $fields): string
    {
        $values = collect($data)->only($fields)->sortKeys()->all();

        return hash('sha256', json_encode(['user' => auth()->id(), 'values' => $values], JSON_THROW_ON_ERROR));
    }

    public static function requireSame(Model $record, string $fingerprint): void
    {
        if (! hash_equals((string) $record->request_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['operation' => __('This submission was already used for different details. Reload the form and try again.')]);
        }
    }
}
