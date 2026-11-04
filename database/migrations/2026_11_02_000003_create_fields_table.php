<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('area_ha', 8, 2)->nullable();
            $table->json('geometry')->nullable();
            $table->string('soil_type', 50)->nullable();
            $table->decimal('soil_ph', 3, 1)->nullable();
            $table->decimal('soil_organic_matter_pct', 4, 2)->nullable();
            $table->enum('irrigation_type', ['secano', 'riego', 'mixto'])->default('secano');
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
