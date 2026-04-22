<?php

namespace Modules\Administration\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
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
            'id' => $settings->id,
            'app_name' => $settings->app_name,
            'app_description' => $settings->app_description,
            'logo_path' => $settings->logo_path,
            'logo_url' => $logoUrl,
            'favicon_path' => $settings->favicon_path,
            'favicon_url' => $faviconUrl,
            'office_name' => $settings->office_name,
            'office_address' => $settings->office_address,
            'office_phone' => $settings->office_phone,
            'office_email' => $settings->office_email,
            'created_at' => $settings->created_at,
            'updated_at' => $settings->updated_at,
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'required|string|max:255',
            'app_description' => 'nullable|string',
            'office_name' => 'nullable|string|max:255',
            'office_address' => 'nullable|string',
            'office_phone' => 'nullable|string|max:50',
            'office_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = AppSetting::getSettings();
        $settings->update($validator->validated());

        return response()->json($settings);
    }

    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|file|mimes:png,jpg,jpeg,svg,webp|mimetypes:image/png,image/jpeg,image/jpg,image/svg+xml,image/svg,image/webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('logo');
        
        if ($file->getClientMimeType() !== 'image/svg+xml') {
            try {
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo !== false) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                    
                    if ($width > 512 || $height > 512) {
                        return response()->json(['errors' => ['logo' => ['Image dimensions must be 512x512 pixels or less.']]], 422);
                    }
                    
                    if ($width < 32 || $height < 32) {
                        return response()->json(['errors' => ['logo' => ['Image dimensions must be at least 32x32 pixels.']]], 422);
                    }
                }
            } catch (\Exception $e) {
                return response()->json(['errors' => ['logo' => ['Failed to process image. Please try a different image.']]], 422);
            }
        }

        $settings = AppSetting::getSettings();

        if ($settings->logo_path) {
            if (Storage::disk('public')->exists($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }
        }

        $path = $file->store('logos', 'public');

        $settings->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'logo_path' => $path,
            'logo_url' => asset('storage/' . $path) . '?v=' . time(),
        ]);
    }

    public function uploadFavicon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'favicon' => 'required|file|mimes:png,ico,svg,webp|mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml,image/svg,image/webp|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $file = $request->file('favicon');
        
        if ($file->getClientMimeType() !== 'image/svg+xml' && $file->getClientMimeType() !== 'image/x-icon' && $file->getClientMimeType() !== 'image/vnd.microsoft.icon') {
            try {
                $imageInfo = getimagesize($file->getRealPath());
                if ($imageInfo !== false) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                    
                    if ($width > 256 || $height > 256) {
                        return response()->json(['errors' => ['favicon' => ['Image dimensions must be 256x256 pixels or less.']]], 422);
                    }
                    
                    if ($width < 16 || $height < 16) {
                        return response()->json(['errors' => ['favicon' => ['Image dimensions must be at least 16x16 pixels.']]], 422);
                    }
                }
            } catch (\Exception $e) {
                return response()->json(['errors' => ['favicon' => ['Failed to process image. Please try a different image.']]], 422);
            }
        }

        $settings = AppSetting::getSettings();

        if ($settings->favicon_path) {
            if (Storage::disk('public')->exists($settings->favicon_path)) {
                Storage::disk('public')->delete($settings->favicon_path);
            }
        }

        $path = $file->store('favicons', 'public');

        $settings->update(['favicon_path' => $path]);

        return response()->json([
            'message' => 'Favicon uploaded successfully',
            'favicon_path' => $path,
            'favicon_url' => asset('storage/' . $path) . '?v=' . time(),
        ]);
    }
}
