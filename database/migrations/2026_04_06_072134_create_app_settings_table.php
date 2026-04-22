<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Digital Guest Book');
            $table->text('app_description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('office_name')->nullable();
            $table->text('office_address')->nullable();
            $table->string('office_phone', 50)->nullable();
            $table->string('office_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};