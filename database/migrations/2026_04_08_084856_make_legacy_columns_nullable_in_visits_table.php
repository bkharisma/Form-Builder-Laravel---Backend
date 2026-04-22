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
        Schema::table('visits', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('institution')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('purpose')->nullable()->change();
            $table->string('host_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->text('address')->nullable(false)->change();
            $table->string('institution')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('purpose')->nullable(false)->change();
            $table->string('host_name')->nullable(false)->change();
        });
    }
};
