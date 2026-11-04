<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->unsignedSmallInteger('max_extra_beds')->default(0);
            $table->unsignedInteger('extra_bed_rate')->default(0);
        });
        Schema::table('reservations', function (Blueprint $table): void {
            $table->json('companions')->nullable();
            $table->unsignedSmallInteger('extra_beds')->default(0);
            $table->unsignedSmallInteger('extra_bed_nights')->default(1);
            $table->unsignedInteger('extra_bed_rate')->default(0);
        });
        Schema::create('stay_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('gender', 20)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('type', 10)->default('adult');
            $table->unsignedTinyInteger('age')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['stay_id', 'is_primary']);
        });
        Schema::table('stay_identity_documents', function (Blueprint $table): void {
            $table->foreignId('stay_guest_id')->nullable()->constrained('stay_guests')->restrictOnDelete();
        });
        Schema::create('stay_extra_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stay_id')->constrained()->restrictOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('nights');
            $table->unsignedInteger('rate');
            $table->timestamp('returned_at')->nullable();
            $table->uuid('request_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['stay_id', 'returned_at']);
        });
        DB::table('stays')->orderBy('id')->chunkById(200, function ($stays): void {
            foreach ($stays as $stay) {
                $id = DB::table('stay_guests')->insertGetId([
                    'stay_id' => $stay->id, 'name' => $stay->guest_name, 'gender' => $stay->gender,
                    'phone' => $stay->phone, 'type' => 'adult', 'is_primary' => true,
                    'created_at' => $stay->created_at, 'updated_at' => $stay->updated_at,
                ]);
                DB::table('stay_identity_documents')->where('stay_id', $stay->id)->update(['stay_guest_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('stay_extra_beds')->exists() || DB::table('stay_guests')->where('is_primary', false)->exists()
            || DB::table('reservations')->where('extra_beds', '>', 0)->exists()
            || DB::table('reservations')->whereNotNull('companions')->where('companions', '!=', '[]')->exists()) {
            throw new RuntimeException('Group occupancy records exist. Restore a complete backup instead of discarding guests or extra-bed history.');
        }
        Schema::dropIfExists('stay_extra_beds');
        Schema::table('stay_identity_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('stay_guest_id'));
        Schema::dropIfExists('stay_guests');
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['companions', 'extra_beds', 'extra_bed_nights', 'extra_bed_rate']));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['capacity', 'max_extra_beds', 'extra_bed_rate']));
    }
};
