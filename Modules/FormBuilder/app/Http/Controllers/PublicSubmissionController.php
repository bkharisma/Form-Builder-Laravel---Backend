<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Http\Requests\StoreSubmissionRequest;
use Modules\FormBuilder\Models\Submission;
use Modules\FormBuilder\Models\SubmissionFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicSubmissionController extends Controller
{
    public function store(StoreSubmissionRequest $request, string $formSlug): JsonResponse
    {
        $formConfig = $request->getFormConfig();

        if (!$formConfig) {
            return response()->json([
                'message' => 'Form not found.',
            ], 404);
        }

        $dynamicData = $request->input('dynamic_data', []);
        $conditionalLogicService = app(\Modules\FormBuilder\Services\ConditionalLogicService::class);
        $fields = collect($formConfig->fields)->filter(fn($f) => $f['enabled'] ?? false);

        $cleanData = [];
        foreach ($fields as $field) {
            if ($conditionalLogicService->isFieldVisible($field, $dynamicData)) {
                if (array_key_exists($field['id'], $dynamicData)) {
                    $cleanData[$field['id']] = $dynamicData[$field['id']];
                }
            }
        }
        $dynamicData = $cleanData;

        foreach ($fields as $field) {
            $value = $dynamicData[$field['id']] ?? null;

            if ($field['type'] === 'signature' && !empty($value) && is_string($value) && str_starts_with($value, 'data:image/')) {
                $mimeType = $this->extractMimeType($value);
                $extension = $this->mimeToExtension($mimeType);

                $imageData = explode(',', $value);
                if (count($imageData) === 2) {
                    $binaryData = base64_decode($imageData[1], true);
                    if ($binaryData !== false) {
                        $storedName = Str::uuid() . '.' . $extension;
                        $date = now()->format('Y-m-d');
                        $path = "{$formSlug}/pending/{$date}/signatures/{$storedName}";
                        Storage::disk('submission_files')->put($path, $binaryData);

                        $submissionFile = SubmissionFile::create([
                            'submission_id' => null,
                            'form_slug' => $formSlug,
                            'field_id' => $field['id'],
                            'original_name' => 'signature.' . $extension,
                            'stored_name' => $storedName,
                            'mime_type' => $mimeType,
                            'file_size' => strlen($binaryData),
                            'path' => $path,
                        ]);

                        $dynamicData[$field['id']] = (string) $submissionFile->id;
                    }
                }
            }
        }

        $submission = Submission::create([
            'form_config_id' => $formConfig->id,
            'dynamic_data'   => $dynamicData,
            'submitted_at'   => now(),
        ]);

        $this->movePendingFiles($formSlug, $submission->id);



        return response()->json($submission, 201);
    }

    private function movePendingFiles(string $formSlug, int $submissionId): void
    {
        $disk = Storage::disk('submission_files');
        $date = now()->format('Y-m-d');

        $pendingFiles = SubmissionFile::where('form_slug', $formSlug)
            ->whereNull('submission_id')
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        foreach ($pendingFiles as $file) {
            $oldPath = $file->path;
            $newPath = $this->buildFinalPath($formSlug, $submissionId, $date, $oldPath);

            if ($oldPath !== $newPath && $disk->exists($oldPath)) {
                $disk->move($oldPath, $newPath);
                $file->path = $newPath;
            }

            $file->submission_id = $submissionId;
            $file->save();
        }
    }

    private function buildFinalPath(string $formSlug, int $submissionId, string $date, string $oldPath): string
    {
        $type = $this->extractTypeFromPath($oldPath);
        $storedName = basename($oldPath);

        return "{$formSlug}/{$submissionId}/{$date}/{$type}/{$storedName}";
    }

    private function extractTypeFromPath(string $path): string
    {
        if (str_contains($path, '/signatures/')) {
            return 'signatures';
        }
        if (str_contains($path, '/images/')) {
            return 'images';
        }
        if (str_contains($path, '/files/')) {
            return 'files';
        }

        $parts = explode('/', $path);
        return $parts[0] ?? 'files';
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
