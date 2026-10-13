<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff');
            $table->boolean('active')->default(true);
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('type');
            $table->unsignedBigInteger('rate');
            $table->string('status')->default('available')->index();
            $table->timestamps();
        });
        Schema::create('stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            // A database constraint prevents two active guests occupying the same room.
            $table->foreignId('active_room_id')->nullable()->unique()->constrained('rooms')->restrictOnDelete();
            $table->string('guest_name');
            $table->string('phone', 40);
            $table->text('notes')->nullable();
            $table->dateTime('check_in_at')->index();
            $table->dateTime('expected_check_out_at');
            $table->dateTime('check_out_at')->nullable()->index();
            $table->unsignedBigInteger('room_charge');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action')->index();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'payments', 'charges', 'stays', 'rooms'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'active']));
    }
};
