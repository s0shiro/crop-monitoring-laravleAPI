<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_plantings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variety_id')->constrained()->cascadeOnDelete();
            $table->date('planting_date');
            $table->date('expected_harvest_date')->nullable();
            $table->decimal('area_planted', 10, 2);
            $table->decimal('harvested_area', 10, 2)->default(0);
            $table->decimal('remaining_area', 10, 2);
            $table->decimal('damaged_area', 10, 2)->default(0); 
            $table->decimal('quantity', 10, 2);
            $table->decimal('expenses', 10, 2)->nullable();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->text('remarks');
            $table->enum('status', ['standing', 'harvest', 'partially harvested', 'harvested']);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('municipality');
            $table->string('barangay');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_plantings');
    }
};