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
        Schema::create('nasa_agro_data', function (Blueprint $table) {
            $table->id();
            $table->decimal('lat', 10, 7);
            $table->decimal('lon', 10, 7);
            $table->date('date');
            $table->float('temp')->nullable();
            $table->float('precip')->nullable();
            $table->float('humidity')->nullable();
            $table->float('ndvi_value')->nullable();
            $table->timestamps();

            $table->unique(['lat', 'lon', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nasa_agro_data');
    }
};
