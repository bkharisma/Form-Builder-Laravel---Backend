<?php

namespace Modules\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Http\Requests\FormConfigRequest;
use Modules\FormBuilder\Http\Resources\FormConfigResource;
use Modules\FormBuilder\Models\FormConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FormConfig::withCount('submissions')
            ->with(['createdBy', 'updatedBy']);

        if ($request->user() && $request->user()->role === 'user') {
            $query->where('created_by', $request->user()->id);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'LIKE', "%{$search}%");
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $forms = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => FormConfigResource::collection($forms),
            'meta' => [
                'current_page' => $forms->currentPage(),
                'last_page' => $forms->lastPage(),
                'per_page' => $forms->perPage(),
                'total' => $forms->total(),
            ],
        ]);
    }

    public function show(Request $request, FormConfig $formConfig): JsonResponse
    {
        if ($request->user() && $request->user()->role === 'user' && $formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this form.');
        }

        $formConfig->loadCount('submissions');
        $formConfig->load(['createdBy', 'updatedBy']);

        return response()->json([
            'data' => new FormConfigResource($formConfig),
        ]);
    }

    public function store(FormConfigRequest $request): JsonResponse
    {
        $config = FormConfig::create([
            'title' => $request->input('title', ''),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'published'),
            'fields' => $request->input('fields', []),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $config->loadCount('submissions');
        $config->load(['createdBy', 'updatedBy']);

        return response()->json([
            'data' => new FormConfigResource($config),
        ], 201);
    }

    public function update(FormConfigRequest $request, FormConfig $formConfig): JsonResponse
    {
        if ($request->user() && $request->user()->role === 'user' && $formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this form.');
        }

        $updateData = [
            'updated_by' => $request->user()->id,
        ];

        if ($request->has('title')) {
            $updateData['title'] = $request->input('title');
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->input('description');
        }
        if ($request->has('status')) {
            $updateData['status'] = $request->input('status');
        }
        if ($request->has('fields')) {
            $updateData['fields'] = $request->input('fields');
        }


        $formConfig->update($updateData);
        $formConfig->loadCount('submissions');
        $formConfig->load(['createdBy', 'updatedBy']);

        return response()->json([
            'data' => new FormConfigResource($formConfig),
        ]);
    }

    public function destroy(Request $request, FormConfig $formConfig): JsonResponse
    {
        if ($request->user() && $request->user()->role === 'user' && $formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this form.');
        }

        if ($formConfig->submissions()->exists()) {
            return response()->json([
                'message' => 'you have submission exist failed to delete'
            ], 422);
        }

        $formConfig->delete();

        return response()->json(null, 204);
    }
}
