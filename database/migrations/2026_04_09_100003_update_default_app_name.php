<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('app_name')->default('Form Builder')->change();
        });

        DB::table('app_settings')->where('app_name', 'Digital Guest Book')->update(['app_name' => 'Form Builder']);
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('app_name')->default('Digital Guest Book')->change();
        });

        DB::table('app_settings')->where('app_name', 'Form Builder')->update(['app_name' => 'Digital Guest Book']);
    }
};