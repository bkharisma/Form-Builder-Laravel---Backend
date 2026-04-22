<?php

namespace Modules\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Http\Requests\DynamicReportRequest;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicReportController extends Controller
{
    private const CHART_COMPATIBLE_TYPES = ['select', 'checkbox', 'text', 'date', 'radio'];

    private function resolveFormConfig(Request $request): FormConfig
    {
        $request->validate(['form_slug' => 'required|string']);

        $formConfig = FormConfig::where('slug', $request->input('form_slug'))->firstOrFail();

        if ($request->user() && $request->user()->role === 'user' && $formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to this form\'s reports.');
        }

        return $formConfig;
    }

    public function fields(Request $request): JsonResponse
    {
        $config = $this->resolveFormConfig($request);

        $chartFields = collect($config->fields)
            ->filter(fn($field) => in_array($field['type'], self::CHART_COMPATIBLE_TYPES))
            ->map(function ($field) {
                $result = [
                    'id' => $field['id'],
                    'type' => $field['type'],
                    'label' => $field['label'],
                ];

                if (($field['type'] === 'select' || $field['type'] === 'radio') && isset($field['options'])) {
                    $result['options'] = $field['options'];
                }

                return $result;
            })
            ->values()
            ->all();

        return response()->json([
            'fields' => $chartFields,
        ]);
    }

    public function aggregate(DynamicReportRequest $request): JsonResponse
    {
        $fieldId = $request->validated('field_id');
        $dateFrom = $request->validated('date_from') ?? now()->subDays(30)->toDateString();
        $dateTo = $request->validated('date_to') ?? now()->toDateString();

        $config = $this->resolveFormConfig($request);

        $fieldDefinition = collect($config->fields)
            ->first(fn($field) => $field['id'] === $fieldId);

        if (!$fieldDefinition) {
            return response()->json([
                'message' => 'Field not found in the active FormConfig.',
                'errors' => ['field_id' => ["The field '{$fieldId}' does not exist in the active FormConfig."]],
            ], 422);
        }

        if (!in_array($fieldDefinition['type'], self::CHART_COMPATIBLE_TYPES)) {
            return response()->json([
                'message' => 'Field type is not chart-compatible.',
                'errors' => ['field_id' => ["The field type '{$fieldDefinition['type']}' is not chart-compatible. Compatible types: " . implode(', ', self::CHART_COMPATIBLE_TYPES)]],
            ], 422);
        }

        $fieldInfo = [
            'id' => $fieldDefinition['id'],
            'type' => $fieldDefinition['type'],
            'label' => $fieldDefinition['label'],
        ];

        if (($fieldDefinition['type'] === 'select' || $fieldDefinition['type'] === 'radio') && isset($fieldDefinition['options'])) {
            $fieldInfo['options'] = $fieldDefinition['options'];
        }

        $fieldType = $fieldDefinition['type'];

        if ($fieldType === 'date') {
            $data = $this->aggregateDateField($config->id, $fieldId, $dateFrom, $dateTo);
        } elseif ($fieldType === 'checkbox') {
            $data = $this->aggregateCheckboxField($config->id, $fieldId, $dateFrom, $dateTo);
        } elseif ($fieldType === 'text') {
            $data = $this->aggregateTextField($config->id, $fieldId, $dateFrom, $dateTo);
        } elseif ($fieldType === 'radio') {
            $data = $this->aggregateSelectField($config->id, $fieldId, $dateFrom, $dateTo);
        } else {
            $data = $this->aggregateSelectField($config->id, $fieldId, $dateFrom, $dateTo);
        }

        return response()->json([
            'field' => $fieldInfo,
            'data' => $data,
        ]);
    }

    private function aggregateSelectField(int $configId, string $fieldId, string $dateFrom, string $dateTo): array
    {
        $results = Submission::query()
            ->where('form_config_id', $configId)
            ->whereDate('submitted_at', '>=', $dateFrom)
            ->whereDate('submitted_at', '<=', $dateTo)
            ->select(
                DB::raw("json_extract(dynamic_data, '$.{$fieldId}') as value"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull(DB::raw("json_extract(dynamic_data, '$.{$fieldId}')"))
            ->groupBy('value')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        return $results->map(fn($item) => [
            'name' => $this->stripJsonQuotes($item->value),
            'count' => $item->count,
        ])->all();
    }

    private function aggregateTextField(int $configId, string $fieldId, string $dateFrom, string $dateTo): array
    {
        $results = Submission::query()
            ->where('form_config_id', $configId)
            ->whereDate('submitted_at', '>=', $dateFrom)
            ->whereDate('submitted_at', '<=', $dateTo)
            ->select(
                DB::raw("json_extract(dynamic_data, '$.{$fieldId}') as value"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull(DB::raw("json_extract(dynamic_data, '$.{$fieldId}')"))
            ->groupBy('value')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        return $results->map(fn($item) => [
            'name' => $this->stripJsonQuotes($item->value),
            'count' => $item->count,
        ])->all();
    }

    private function aggregateCheckboxField(int $configId, string $fieldId, string $dateFrom, string $dateTo): array
    {
        $results = Submission::query()
            ->where('form_config_id', $configId)
            ->whereDate('submitted_at', '>=', $dateFrom)
            ->whereDate('submitted_at', '<=', $dateTo)
            ->select(
                DB::raw("json_extract(dynamic_data, '$.{$fieldId}') as value"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('value')
            ->get();

        $grouped = [];
        foreach ($results as $item) {
            $rawValue = $this->stripJsonQuotes($item->value);
            $isTrue = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN);
            $label = $isTrue ? 'Yes' : 'No';
            if (!isset($grouped[$label])) {
                $grouped[$label] = 0;
            }
            $grouped[$label] += $item->count;
        }

        $data = [];
        foreach ($grouped as $label => $count) {
            $data[] = ['name' => $label, 'count' => $count];
        }

        usort($data, fn($a, $b) => $b['count'] <=> $a['count']);

        return $data;
    }

    private function aggregateDateField(int $configId, string $fieldId, string $dateFrom, string $dateTo): array
    {
        $results = Submission::query()
            ->where('form_config_id', $configId)
            ->whereDate('submitted_at', '>=', $dateFrom)
            ->whereDate('submitted_at', '<=', $dateTo)
            ->select(
                DB::raw("json_extract(dynamic_data, '$.{$fieldId}') as value"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotNull(DB::raw("json_extract(dynamic_data, '$.{$fieldId}')"))
            ->groupBy('value')
            ->orderBy('value')
            ->get();

        return $results->map(fn($item) => [
            'date' => $this->stripJsonQuotes($item->value),
            'count' => $item->count,
        ])->all();
    }

    private function stripJsonQuotes(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
