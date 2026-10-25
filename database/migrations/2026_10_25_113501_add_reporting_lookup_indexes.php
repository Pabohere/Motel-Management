<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('created_at');
            $table->index(['stay_id', 'created_at']);
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->index(['stay_id', 'created_at']);
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->index(['room_id', 'status', 'arrival_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['stay_id', 'created_at']);
        });
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropIndex(['stay_id', 'created_at']);
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'status', 'arrival_at']);
        });
    }
};
