<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_identity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        DB::table('stays')->whereNotNull('identity_document_path')->orderBy('id')->chunkById(100, function (Collection $stays): void {
            foreach ($stays as $stay) {
                DB::table('stay_identity_documents')->insert([
                    'stay_id' => $stay->id,
                    'path' => $stay->identity_document_path,
                    'created_at' => $stay->created_at,
                    'updated_at' => $stay->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_identity_documents');
    }
};
