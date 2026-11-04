<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rotation_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->enum('season', ['verano', 'invierno', 'todo_año']);
            $table->unsignedInteger('duration_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['rotation_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rotation_steps');
    }
};
