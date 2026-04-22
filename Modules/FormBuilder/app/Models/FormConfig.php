<?php

namespace Modules\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'description', 'status', 'fields', 'created_by', 'updated_by'])]
class FormConfig extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'fields'              => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FormConfig $model) {
            if (empty($model->slug)) {
                $baseSlug = Str::slug($model->title ?: 'form');
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                $model->slug = $slug;
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'form_config_id');
    }
}
