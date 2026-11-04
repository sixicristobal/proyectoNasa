<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scientific_name')->nullable();
            $table->string('family', 100)->nullable();
            $table->boolean('nitrogen_fixing')->default(false);
            $table->boolean('deep_root')->default(false);
            $table->boolean('drought_tolerant')->default(false);
            $table->enum('water_demand', ['bajo', 'medio', 'alto'])->default('medio');
            $table->unsignedInteger('growing_days')->nullable();
            $table->json('planting_months')->nullable();
            $table->json('harvest_months')->nullable();
            $table->enum('market_value', ['bajo', 'medio', 'alto'])->default('medio');
            $table->enum('forage_value', ['bajo', 'medio', 'alto'])->default('medio');
            $table->enum('erosion_control', ['bajo', 'medio', 'alto'])->default('medio');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
