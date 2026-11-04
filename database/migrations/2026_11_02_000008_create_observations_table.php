<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->enum('source', [
                'POWER',
                'MOD13Q1',
                'SMAP',
                'ECOSTRESS',
                'GPM',
                'SRTM',
                'MODIS_LST',
                'LANDSAT',
            ]);
            $table->string('variable', 50);
            $table->decimal('value', 12, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('observed_at');
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['field_id', 'source', 'variable', 'observed_at'], 'obs_field_source_var_date_unique');
            $table->index(['field_id', 'observed_at'], 'idx_obs_field_date');
            $table->index('variable', 'idx_obs_variable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
