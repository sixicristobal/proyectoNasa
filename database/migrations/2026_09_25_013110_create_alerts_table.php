<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('risk_type', 50);
            $table->text('message');
            $table->date('data_date')->nullable();
            $table->string('source', 50)->default('n8n-gemini');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['risk_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
