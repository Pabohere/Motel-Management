<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sample rooms only; account credentials are never committed or pre-set.
        foreach ([...range(101, 108), ...range(201, 204)] as $number) {
            Room::firstOrCreate(['number' => (string) $number], [
                'floor' => intdiv($number, 100),
                'type' => $number < 200 ? 'Standard' : 'Deluxe',
                'rate' => $number < 200 ? 35000 : 50000,
                'status' => 'available',
            ]);
        }
    }
}
