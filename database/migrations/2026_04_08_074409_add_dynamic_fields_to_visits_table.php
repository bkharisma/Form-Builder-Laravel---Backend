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
            $table->foreignId('form_config_id')->nullable()->after('id')->constrained('form_configs')->nullOnDelete();
            $table->json('dynamic_data')->nullable()->after('host_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['form_config_id']);
            $table->dropColumn(['form_config_id', 'dynamic_data']);
        });
    }
};
