<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function submissionsOverTime(Request $request)
    {
        $request->validate(['form_slug' => 'required|string']);
        $formConfig = FormConfig::where('slug', $request->input('form_slug'))->firstOrFail();

        $dateFrom = $request->query('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $data = Submission::query()
            ->where('form_config_id', $formConfig->id)
            ->whereDate('submitted_at', '>=', $dateFrom)
            ->whereDate('submitted_at', '<=', $dateTo)
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ]);

        return response()->json($data);
    }
}
