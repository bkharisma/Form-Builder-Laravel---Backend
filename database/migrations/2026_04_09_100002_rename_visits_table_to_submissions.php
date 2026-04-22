<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('visits', 'submissions');
    }

    public function down(): void
    {
        Schema::rename('submissions', 'visits');
    }
};