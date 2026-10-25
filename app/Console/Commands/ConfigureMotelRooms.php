<?php

namespace App\Console\Commands;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConfigureMotelRooms extends Command
{
    protected $signature = 'motel:configure-rooms {--apply : Apply the four-floor room configuration}';

    protected $description = 'Configure 40 rooms: 4 Single, 4 Couple and 2 Family rooms per floor';

    public function handle(): int
    {
        $plan = [];
        foreach (range(1, 4) as $floor) {
            foreach (range(1, 10) as $position) {
                [$type, $rate] = match (true) {
                    $position <= 4 => ['Single Room', 35000],
                    $position <= 8 => ['Couple Room', 50000],
                    default => ['Family Room', 70000],
                };
                $plan[(string) ($floor * 100 + $position)] = ['floor' => $floor, 'type' => $type, 'rate' => $rate];
            }
        }
        $this->table(['Floor', 'Rooms', 'Single (35,000 MMK)', 'Couple (50,000 MMK)', 'Family (70,000 MMK)'], array_map(
            fn (int $floor): array => [$floor, ($floor * 100 + 1).'–'.($floor * 100 + 10), 4, 4, 2], range(1, 4)
        ));
        if (! $this->option('apply')) {
            $this->info('Preview only. Use --apply to save. Unused rooms outside this plan will be archived; history is retained.');

            return self::SUCCESS;
        }
        try {
            DB::transaction(function () use ($plan): void {
                $rooms = Room::withTrashed()->lockForUpdate()->get();
                $extras = $rooms->whereNull('deleted_at')->reject(fn (Room $room): bool => isset($plan[$room->number]));
                foreach ($extras as $room) {
                    if ($room->status === 'occupied' || $room->activeStay()->exists() || $room->reservations()->where('status', 'booked')->exists()) {
                        throw new RuntimeException('Room '.$room->number.' has an active stay or booking. No room configuration was changed.');
                    }
                }
                foreach ($plan as $number => $settings) {
                    $floor = Floor::firstOrCreate(['number' => $settings['floor']]);
                    if ($floor->wasRecentlyCreated) {
                        Audit::record('floor.created', $floor, null, $floor->toArray());
                    }
                    $type = RoomType::firstOrCreate(['name' => $settings['type']]);
                    if ($type->wasRecentlyCreated) {
                        Audit::record('room_type.created', $type, null, $type->toArray());
                    }
                    $room = $rooms->first(fn (Room $room): bool => $room->number === (string) $number);
                    $before = $room?->toArray();
                    $room ??= new Room(['number' => (string) $number]);
                    $room->fill(['floor_id' => $floor->id, 'room_type_id' => $type->id, 'rate' => $settings['rate']]);
                    if ($room->trashed()) {
                        $room->deleted_at = null;
                    }
                    if (! $room->exists || $room->isDirty()) {
                        $room->save();
                        Audit::record($before ? 'room.updated' : 'room.created', $room, $before, $room->toArray());
                    }
                }
                foreach ($extras as $room) {
                    $before = $room->toArray();
                    $room->delete();
                    Audit::record('room.archived', $room, $before, $room->toArray());
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Room configuration complete: 4 floors, 10 rooms per floor, 40 active rooms. Existing history retained.');

        return self::SUCCESS;
    }
}
