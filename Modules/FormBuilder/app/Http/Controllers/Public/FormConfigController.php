<?php

namespace Modules\FormBuilder\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Http\Resources\PublicFormConfigResource;
use Modules\FormBuilder\Models\FormConfig;
use Illuminate\Http\JsonResponse;

class FormConfigController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $config = FormConfig::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'data' => new PublicFormConfigResource($config),
        ]);
    }
}
