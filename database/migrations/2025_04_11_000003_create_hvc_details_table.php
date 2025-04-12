<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hvc_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crop_planting_id')->constrained()->cascadeOnDelete();
            $table->string('classification');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hvc_details');
    }
};