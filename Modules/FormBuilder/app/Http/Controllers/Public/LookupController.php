<?php

namespace Modules\FormBuilder\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function __invoke(Request $request, string $formSlug): JsonResponse
    {
        $form = FormConfig::where('slug', $formSlug)
            ->where('status', 'published')
            ->first();

        if (!$form) {
            return response()->json(['message' => 'Form not found'], 404);
        }

        $fieldId = $request->query('field_id');
        $value = $request->query('value');

        if (!$fieldId || !$value || strlen($value) < 5) {
            return response()->json(['matches' => []]);
        }

        $uniqueField = collect($form->fields)->firstWhere('id', $fieldId);
        if (!$uniqueField || !($uniqueField['is_unique'] ?? false)) {
            return response()->json(['matches' => []]);
        }

        $matches = Submission::where('form_config_id', $form->id)
            ->where('dynamic_data->' . $fieldId, $value)
            ->latest('submitted_at')
            ->limit(5)
            ->get(['id', 'dynamic_data', 'submitted_at']);

        $formattedMatches = $matches->map(function ($submission) {
            $data = $submission->dynamic_data;
            return [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toISOString(),
                'summary' => [
                    'name' => $this->findFieldByName($data, ['name', 'nama', 'full_name']),
                    'email' => $this->findFieldByName($data, ['email', 'email_address']),
                    'date' => $submission->submitted_at->format('M d, Y'),
                ],
                'data' => $data,
            ];
        });

        return response()->json(['matches' => $formattedMatches]);
    }

    private function findFieldByName(array $data, array $possibleNames): ?string
    {
        foreach ($possibleNames as $name) {
            foreach ($data as $key => $value) {
                if (is_string($key) && str_contains(strtolower($key), $name)) {
                    return is_string($value) ? $value : null;
                }
            }
        }
        return null;
    }
}
