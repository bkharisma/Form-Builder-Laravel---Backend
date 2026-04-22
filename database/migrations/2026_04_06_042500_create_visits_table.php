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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->string('institution');
            $table->string('phone');
            $table->string('purpose');
            $table->string('host_name');
            $table->dateTime('check_in_at');
            $table->index('check_in_at');
            $table->index('institution');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
