<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_planting_id')->constrained()->cascadeOnDelete();
            $table->string('classification');
            $table->string('water_supply');
            $table->string('land_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rice_details');
    }
};