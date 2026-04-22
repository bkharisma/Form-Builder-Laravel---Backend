<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_configs', function (Blueprint $table) {
            $table->string('title')->default('')->after('id');
            $table->string('slug')->unique()->after('title');
            $table->text('description')->nullable()->after('slug');
            $table->string('status')->default('published')->after('description');
        });

        // Backfill existing rows with a default slug
        $rows = DB::table('form_configs')->get();
        foreach ($rows as $row) {
            DB::table('form_configs')
                ->where('id', $row->id)
                ->update([
                    'slug' => 'form-' . $row->id,
                    'title' => 'Form #' . $row->id,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_configs', function (Blueprint $table) {
            $table->dropColumn(['title', 'slug', 'description', 'status']);
        });
    }
};
