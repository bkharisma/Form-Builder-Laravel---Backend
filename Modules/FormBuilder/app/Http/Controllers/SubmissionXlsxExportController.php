<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\FormConfig;
use Modules\FormBuilder\Models\Submission;
use Modules\FormBuilder\Models\SubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionXlsxExportController extends Controller
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
        $submissions = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $dynamicHeaders = collect($formConfig->fields)
            ->filter(fn($f) => $f['enabled'] ?? false)
            ->sortBy('order')
            ->pluck('label')
            ->toArray();

        $staticHeaders = ['ID', 'Submitted At', 'Created At'];
        $allHeaders = array_merge($dynamicHeaders, $staticHeaders);
        
        $sheet->fromArray($allHeaders, null, 'A1');

        $baseUrl = $request->getSchemeAndHttpHost() . '/api';

        $row = 2;
        foreach ($submissions as $submission) {
            $rowData = [];

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
                    $rowData[] = $value;
                }
            } elseif ($submission->dynamic_data) {
                foreach ($submission->dynamic_data as $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $rowData[] = $value;
                }
            } else {
                $fieldCount = collect($formConfig->fields)
                    ->filter(fn($f) => $f['enabled'] ?? false)
                    ->count();
                $rowData = array_fill(0, $fieldCount, '');
            }

            $rowData[] = $submission->id;
            $rowData[] = $submission->submitted_at?->format('Y-m-d H:i:s');
            $rowData[] = $submission->created_at?->format('Y-m-d H:i:s');

            $sheet->fromArray($rowData, null, "A{$row}");
            $row++;
        }

        $exportSlug = $formConfig->slug;
        $filename = $exportSlug . '-export-' . now()->format('Y-m-d') . '.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, $headers);
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
