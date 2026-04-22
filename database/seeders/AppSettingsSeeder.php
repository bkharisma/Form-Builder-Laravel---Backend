<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::firstOrCreate(
            [],
            ['app_name' => 'Kopega Poltekpar Palembang APP']
        );
    }
}