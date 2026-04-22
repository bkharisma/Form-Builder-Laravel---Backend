<?php

namespace Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'form_slug', 'field_id', 'original_name', 'stored_name', 'mime_type', 'file_size', 'path'])]
class SubmissionFile extends Model
{
    use HasFactory;

    protected $table = 'submission_files';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
