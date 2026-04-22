<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Modules\FormBuilder\Models\SubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $request->validate(['form_slug' => 'required|string']);
        $formConfig = FormConfig::where('slug', $request->input('form_slug'))->firstOrFail();

        if ($request->user() && $request->user()->role === 'user' && $formConfig->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized access to export this form.');
        }

        $query = Submission::query()->with('formConfig')
            ->where('form_config_id', $formConfig->id);

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

        $query->orderBy('submitted_at', 'desc');

        $dynamicHeaders = collect($formConfig->fields)
            ->filter(fn($f) => $f['enabled'] ?? false)
            ->sortBy('order')
            ->pluck('label')
            ->toArray();

        $staticHeaders = ['ID', 'Submitted At', 'Created At'];
        $allHeaders = array_merge($dynamicHeaders, $staticHeaders);

        $baseUrl = $request->getSchemeAndHttpHost() . '/api';

        $exportSlug = $formConfig->slug;
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $exportSlug . '-submissions_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($query, $allHeaders, $formConfig, $baseUrl) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $allHeaders);

            $query->chunk(500, function ($submissions) use ($handle, $formConfig, $baseUrl) {
                foreach ($submissions as $submission) {
                    $row = [];

                    if (!empty($formConfig->fields) && $submission->dynamic_data) {
                        $fields = collect($formConfig->fields)
                            ->filter(fn($f) => $f['enabled'] ?? false)
                            ->sortBy('order');

                        foreach ($fields as $field) {
                            $value = $submission->dynamic_data[$field['id']] ?? '';
                            if (is_array($value)) {
                                $value = json_encode($value);
                            }
                            if (in_array($field['type'], ['file_upload', 'image_upload', 'signature']) && !empty($value)) {
                                $value = $this->resolveFileUrl($value, $field['id'], $baseUrl);
                            }
                            $row[] = $value;
                        }
                    } elseif ($submission->dynamic_data) {
                        foreach ($submission->dynamic_data as $value) {
                            if (is_array($value)) {
                                $value = json_encode($value);
                            }
                            $row[] = $value;
                        }
                    } else {
                        $fieldCount = collect($formConfig->fields)
                            ->filter(fn($f) => $f['enabled'] ?? false)
                            ->count();
                        $row = array_fill(0, $fieldCount, '');
                    }

                    $row[] = $submission->id;
                    $row[] = $submission->submitted_at?->format('Y-m-d H:i:s');
                    $row[] = $submission->created_at?->format('Y-m-d H:i:s');

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, $headers);
    }

    private function resolveFileUrl(string $value, string $fieldId, string $baseUrl): string
    {
        if (str_starts_with($value, 'data:image/')) {
            $mimeType = $this->extractMimeType($value);
            $extension = $this->mimeToExtension($mimeType);

            $imageData = explode(',', $value);
            if (count($imageData) === 2) {
                $binaryData = base64_decode($imageData[1], true);
                if ($binaryData !== false) {
                    $storedName = Str::uuid() . '.' . $extension;
                    $path = 'signatures/' . $storedName;
                    Storage::disk('submission_files')->put($path, $binaryData);

                    $submissionFile = SubmissionFile::create([
                        'submission_id' => null,
                        'field_id' => $fieldId,
                        'original_name' => 'signature.' . $extension,
                        'stored_name' => $storedName,
                        'mime_type' => $mimeType,
                        'file_size' => strlen($binaryData),
                        'path' => $path,
                    ]);

                    return $baseUrl . '/upload/' . $submissionFile->id;
                }
            }

            return '';
        }

        $fileRecord = SubmissionFile::find($value);
        if ($fileRecord) {
            return $baseUrl . '/upload/' . $fileRecord->id;
        }

        return '';
    }

    private function extractMimeType(string $dataUrl): string
    {
        preg_match('/^data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+)/', $dataUrl, $matches);
        return $matches[1] ?? 'image/png';
    }

    private function mimeToExtension(string $mimeType): string
    {
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        return $map[$mimeType] ?? 'png';
    }
}
