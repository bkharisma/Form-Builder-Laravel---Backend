<?php

namespace Modules\FormBuilder\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_config_id', 'dynamic_data', 'submitted_at'])]
class Submission extends Model
{
    use HasFactory;

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }

    protected $table = 'submissions';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'dynamic_data' => 'array',
        ];
    }

    public function formConfig(): BelongsTo
    {
        return $this->belongsTo(FormConfig::class, 'form_config_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class);
    }
}
