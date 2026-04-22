<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['form_slug' => 'required|string']);
        $formConfig = FormConfig::where('slug', $request->input('form_slug'))->firstOrFail();

        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $query = Submission::where('form_config_id', $formConfig->id);

        $stats = [
            'today' => (clone $query)->whereDate('submitted_at', $today)->count(),
            'this_week' => (clone $query)->whereDate('submitted_at', '>=', $weekStart)->count(),
            'this_month' => (clone $query)->whereDate('submitted_at', '>=', $monthStart)->count(),
            'total' => (clone $query)->count(),
        ];

        return response()->json($stats);
    }
}
