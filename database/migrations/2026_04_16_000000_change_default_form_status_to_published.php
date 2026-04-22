<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('form_configs')
            ->where('status', 'draft')
            ->update(['status' => 'published']);
    }

    public function down(): void
    {
        DB::table('form_configs')
            ->where('status', 'published')
            ->update(['status' => 'draft']);
    }
};
