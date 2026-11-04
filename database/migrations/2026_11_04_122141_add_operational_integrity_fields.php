<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->bigInteger('amount')->change();
            $table->foreignId('reversal_of_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->uuid('request_key')->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
            $table->string('reference', 120)->nullable();
        });
        Schema::table('stays', function (Blueprint $table): void {
            $table->uuid('request_key')->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
            $table->index(['room_id', 'expected_check_out_at']);
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->ipAddress('ip_address')->nullable();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_view_documents')->default(true);
            $table->boolean('can_upload_documents')->default(true);
        });
        Schema::table('stay_identity_documents', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('housekeeping_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cleaning_started_at')->nullable();
            $table->dateTime('cleaned_at')->nullable();
            $table->dateTime('inspected_at')->nullable();
        });
        DB::table('reservations')->where('status', 'checked_in')->whereIn('id', DB::table('stays')->whereNotNull('check_out_at')->select('reservation_id'))->update(['status' => 'completed']);
    }

    public function down(): void
    {
        if (DB::table('payments')->where('amount', '<', 0)->exists()) {
            throw new RuntimeException('Payment reversals exist. Restore a complete backup instead of discarding the reversal ledger.');
        }
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('housekeeping_by');
            $table->dropColumn(['cleaning_started_at', 'cleaned_at', 'inspected_at']);
        });
        Schema::table('stay_identity_documents', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['can_view_documents', 'can_upload_documents']));
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'subject_id', 'created_at']);
            $table->dropColumn('ip_address');
        });
        Schema::table('stays', function (Blueprint $table): void {
            $table->dropIndex(['room_id', 'expected_check_out_at']);
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'request_fingerprint']);
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropUnique(['request_key']);
            $table->dropColumn(['request_key', 'request_fingerprint', 'reference']);
            $table->unsignedBigInteger('amount')->change();
        });
    }
};
