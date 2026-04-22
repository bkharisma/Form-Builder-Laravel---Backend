<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['institution']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $columns = Schema::getColumnListing('visits');
            $legacyColumns = ['name', 'address', 'institution', 'phone', 'purpose', 'host_name'];

            foreach ($legacyColumns as $column) {
                if (in_array($column, $columns)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->text('address')->nullable();
            $table->string('institution')->nullable();
            $table->string('phone')->nullable();
            $table->string('purpose')->nullable();
            $table->string('host_name')->nullable();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index('institution');
        });
    }
};