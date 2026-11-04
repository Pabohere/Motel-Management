<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sample rooms only; account credentials are never committed or pre-set.
        foreach ([...range(101, 108), ...range(201, 204)] as $number) {
            $floor = Floor::firstOrCreate(['number' => intdiv($number, 100)]);
            $type = RoomType::firstOrCreate(['name' => $number < 200 ? 'Standard' : 'Deluxe']);
            Room::firstOrCreate(['number' => (string) $number], [
                'floor_id' => $floor->id,
                'room_type_id' => $type->id,
                'rate' => $number < 200 ? 35000 : 50000,
                'status' => 'available',
            ]);
        }
    }
}
