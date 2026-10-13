<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sample rooms only; account credentials are never committed or pre-set.
        foreach (range(101, 112) as $number) {
            Room::firstOrCreate(['number' => (string) $number], ['type' => $number < 109 ? 'Standard' : 'Deluxe', 'rate' => $number < 109 ? 35000 : 50000, 'status' => 'available']);
        }
    }
}
