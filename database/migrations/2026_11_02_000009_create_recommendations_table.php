<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rotation_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('rank');
            $table->text('why_suggested')->nullable();
            $table->text('evidence')->nullable();
            $table->text('benefits')->nullable();
            $table->text('risks')->nullable();
            $table->text('information_gaps')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->boolean('requires_local_validation')->default(true);
            $table->timestamps();

            $table->index(['field_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
