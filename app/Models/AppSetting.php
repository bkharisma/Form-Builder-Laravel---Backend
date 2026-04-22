<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['app_name', 'app_description', 'logo_path', 'favicon_path', 'office_name', 'office_address', 'office_phone', 'office_email'])]
class AppSetting extends Model
{
    use HasFactory;

    public static function getSettings(): self
    {
        $settings = self::first();
        if (!$settings) {
            $settings = self::create(['app_name' => 'Form Builder']);
        }
        return $settings;
    }
}