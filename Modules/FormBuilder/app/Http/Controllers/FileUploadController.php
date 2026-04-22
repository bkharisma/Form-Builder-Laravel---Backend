<?php

namespace Modules\FormBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\FormBuilder\Models\SubmissionFile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,ppt,pptx,odt,ods,rtf',
            'form_slug' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $formSlug = $request->input('form_slug');
        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $this->buildPath($formSlug, 'files', $storedName);

        $file->storeAs(dirname($path), $storedName, 'submission_files');

        $submissionFile = SubmissionFile::create([
            'submission_id' => null,
            'form_slug' => $formSlug,
            'field_id' => $request->input('field_id', ''),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $path,
        ]);

        return response()->json([
            'id' => $submissionFile->id,
            'original_name' => $submissionFile->original_name,
            'mime_type' => $submissionFile->mime_type,
            'file_size' => $submissionFile->file_size,
            'url' => route('upload.serve', ['id' => $submissionFile->id]),
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
            'form_slug' => 'nullable|string|max:255',
        ]);

        $file = $request->file('image');
        $formSlug = $request->input('form_slug');
        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $this->buildPath($formSlug, 'images', $storedName);

        $file->storeAs(dirname($path), $storedName, 'submission_files');

        $submissionFile = SubmissionFile::create([
            'submission_id' => null,
            'form_slug' => $formSlug,
            'field_id' => $request->input('field_id', ''),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'path' => $path,
        ]);

        return response()->json([
            'id' => $submissionFile->id,
            'original_name' => $submissionFile->original_name,
            'mime_type' => $submissionFile->mime_type,
            'file_size' => $submissionFile->file_size,
            'url' => route('upload.serve', ['id' => $submissionFile->id]),
        ]);
    }

    public function serveFile(int $id): Response
    {
        $submissionFile = SubmissionFile::findOrFail($id);

        $disk = Storage::disk('submission_files');
        if (!$disk->exists($submissionFile->path)) {
            abort(404);
        }

        $content = $disk->get($submissionFile->path);

        $isImage = str_starts_with($submissionFile->mime_type, 'image/');
        $disposition = $isImage ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type' => $submissionFile->mime_type,
            'Content-Disposition' => $disposition . '; filename="' . $submissionFile->original_name . '"',
        ]);
    }

    private function buildPath(?string $formSlug, string $type, string $storedName): string
    {
        if ($formSlug) {
            $date = now()->format('Y-m-d');
            return "{$formSlug}/pending/{$date}/{$type}/{$storedName}";
        }

        return "{$type}/{$storedName}";
    }
}
