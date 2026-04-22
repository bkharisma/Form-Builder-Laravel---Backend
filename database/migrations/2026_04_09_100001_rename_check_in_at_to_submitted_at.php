<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['check_in_at']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->renameColumn('check_in_at', 'submitted_at');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->renameColumn('submitted_at', 'check_in_at');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index('check_in_at');
        });
    }
};