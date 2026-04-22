<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->string('form_slug')->nullable()->after('submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropColumn('form_slug');
        });
    }
};
