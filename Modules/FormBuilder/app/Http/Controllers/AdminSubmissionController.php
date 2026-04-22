<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Http\Requests\StoreSubmissionRequest;
use Modules\FormBuilder\Http\Requests\UpdateSubmissionRequest;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['form_slug' => 'required|string']);
        $formConfig = FormConfig::where('slug', $request->input('form_slug'))->firstOrFail();

        $query = Submission::with('formConfig')
            ->where('form_config_id', $formConfig->id);

        if ($request->user() && $request->user()->role === 'user') {
            if ($formConfig->created_by !== $request->user()->id) {
                abort(403, 'Unauthorized access to this form\'s submissions.');
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->orWhereJsonContains('dynamic_data', $search);
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('submitted_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('submitted_at', '<=', $dateTo);
        }

        $sortBy = $request->query('sort_by', 'submitted_at');
        $sortDir = $request->query('sort_dir', 'desc');

        $allowedSortColumns = ['submitted_at', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'submitted_at';
        }
        if (!in_array(strtolower($sortDir), ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $query->orderBy($sortBy, $sortDir);

        $perPage = $request->query('per_page', 15);
        $perPage = min(max((int) $perPage, 1), 100);

        $submissions = $query->paginate($perPage);

        return response()->json($submissions);
    }

    public function show(Request $request, Submission $submission): JsonResponse
    {
        $submission->load('formConfig');
        
        if ($request->user() && $request->user()->role === 'user' && $submission->formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this submission.');
        }

        return response()->json($submission);
    }

    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $submission = Submission::create([
            ...$request->validated(),
            'submitted_at' => $request->input('submitted_at', now()),
        ]);

        return response()->json($submission, 201);
    }

    public function update(UpdateSubmissionRequest $request, Submission $submission): JsonResponse
    {
        $submission->load('formConfig');
        if ($request->user() && $request->user()->role === 'user' && $submission->formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this submission.');
        }

        $submission->update($request->validated());

        return response()->json($submission);
    }

    public function destroy(Request $request, Submission $submission): JsonResponse
    {
        $submission->load('formConfig');
        if ($request->user() && $request->user()->role === 'user' && $submission->formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this submission.');
        }

        $submission->delete();

        return response()->json(null, 204);
    }
}
