<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_configs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->index('form_config_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_configs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['form_config_id']);
        });
    }
};