<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->default(1)->index();
        });
        Schema::table('stays', function (Blueprint $table) {
            $table->string('gender', 10)->nullable();
        });

        foreach (DB::table('rooms')->select('id', 'number')->get() as $room) {
            if (preg_match('/^\d{3,}$/', $room->number)) {
                $floor = intdiv((int) $room->number, 100);
                if ($floor >= 1 && $floor <= 99) {
                    DB::table('rooms')->where('id', $room->id)->update(['floor' => $floor]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('floor');
        });
    }
};
