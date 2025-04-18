<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_planting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->date('harvest_date');
            $table->decimal('area_harvested', 10, 2);
            $table->decimal('total_yield', 10, 2);
            $table->decimal('profit', 10, 2)->default(0);
            $table->decimal('damage_quantity', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_reports');
    }
};