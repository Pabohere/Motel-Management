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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('guest_name', 150);
            $table->string('gender', 20);
            $table->string('phone', 40);
            $table->text('notes')->nullable();
            $table->dateTime('arrival_at')->index();
            $table->dateTime('departure_at')->index();
            $table->unsignedBigInteger('room_charge');
            $table->string('status', 20)->default('booked')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('stays', function (Blueprint $table) {
            $table->string('source', 20)->default('walk_in');
            $table->string('payment_term', 20)->default('immediate');
            $table->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable();
        });
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });
        foreach (['supplies' => 'ပစ္စည်းဝယ်ယူ', 'maintenance' => 'ပြင်ဆင်ထိန်းသိမ်း', 'utilities' => 'မီး/ရေ/အင်တာနက်', 'salary' => 'လစာ', 'other' => 'အခြား'] as $key => $name) {
            DB::table('expense_categories')->insert(['key' => $key, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('receipt_path'));
        Schema::table('stays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropColumn(['source', 'payment_term']);
        });
        Schema::dropIfExists('reservations');
    }
};
