<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->timestamps();
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        foreach (DB::table('rooms')->distinct()->pluck('floor') as $number) {
            DB::table('floors')->insert(['number' => $number, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (DB::table('rooms')->distinct()->pluck('type') as $name) {
            DB::table('room_types')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('floor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained()->restrictOnDelete();
        });

        $floors = DB::table('floors')->pluck('id', 'number');
        $types = DB::table('room_types')->pluck('id', 'name');
        foreach (DB::table('rooms')->select('id', 'floor', 'type')->get() as $room) {
            DB::table('rooms')->where('id', $room->id)->update([
                'floor_id' => $floors[$room->floor],
                'room_type_id' => $types[$room->type],
            ]);
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['floor']);
            $table->dropColumn(['floor', 'type']);
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('floor_id')->nullable(false)->change();
            $table->foreignId('room_type_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('floor')->nullable()->index();
            $table->string('type')->nullable();
        });
        foreach (DB::table('rooms')->select('id', 'floor_id', 'room_type_id')->get() as $room) {
            DB::table('rooms')->where('id', $room->id)->update([
                'floor' => DB::table('floors')->where('id', $room->floor_id)->value('number'),
                'type' => DB::table('room_types')->where('id', $room->room_type_id)->value('name'),
            ]);
        }
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('floor_id');
            $table->dropConstrainedForeignId('room_type_id');
            $table->unsignedSmallInteger('floor')->nullable(false)->default(1)->change();
            $table->string('type')->nullable(false)->change();
        });
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('floors');
    }
};
