<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('entry_date')->index();
            $table->string('description');
            $table->string('debit_account', 40);
            $table->string('credit_account', 40);
            $table->unsignedBigInteger('amount');
            $table->string('cash_flow_category', 20)->nullable();
            $table->boolean('is_opening')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
