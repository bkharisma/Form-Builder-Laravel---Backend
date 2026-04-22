<?php

namespace Modules\Administration\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Routing\Controller;

class PublicSettingController extends Controller
{
    public function index()
    {
        $settings = AppSetting::getSettings();
        $logoUrl = null;
        
        if ($settings->logo_path) {
            $fullPath = storage_path('app/public/' . $settings->logo_path);
            $version = file_exists($fullPath) ? filemtime($fullPath) : time();
            $logoUrl = asset('storage/' . $settings->logo_path) . '?v=' . $version;
        }
        
        $faviconUrl = null;
        if ($settings->favicon_path) {
            $fullPath = storage_path('app/public/' . $settings->favicon_path);
            $version = file_exists($fullPath) ? filemtime($fullPath) : time();
            $faviconUrl = asset('storage/' . $settings->favicon_path) . '?v=' . $version;
        }
        
        return response()->json([
            'app_name' => $settings->app_name ?? 'Form Builder',
            'app_description' => $settings->app_description ?? '',
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
            'office_name' => $settings->office_name ?? '',
            'office_address' => $settings->office_address ?? '',
        ]);
    }
}
