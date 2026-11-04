<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('country')->default('Paraguay');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
