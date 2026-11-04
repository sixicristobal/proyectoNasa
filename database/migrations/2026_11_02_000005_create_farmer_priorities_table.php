<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_priorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->unique()->constrained()->cascadeOnDelete();
            $table->tinyInteger('water_conservation_weight')->default(3);
            $table->tinyInteger('income_weight')->default(3);
            $table->tinyInteger('soil_health_weight')->default(3);
            $table->tinyInteger('erosion_weight')->default(3);
            $table->tinyInteger('market_weight')->default(3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_priorities');
    }
};
