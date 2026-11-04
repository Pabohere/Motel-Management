<?php

namespace App\Services;

use App\Models\Room;
use Illuminate\Validation\ValidationException;

class RoomAvailability
{
    /** Call while holding the room lock; adjacent stays use half-open intervals. */
    public function ensureNoReservationOverlap(Room $room, string $start, string $end, ?int $ignoreReservation = null, string $field = 'operation'): void
    {
        if ($room->reservations()->where('status', 'booked')
            ->where('arrival_at', '<', $end)->where('departure_at', '>', $start)
            ->when($ignoreReservation, fn ($query, $id) => $query->whereKeyNot($id))->exists()) {
            throw ValidationException::withMessages([$field => __('This room has another reservation during the selected dates.')]);
        }
    }
}
