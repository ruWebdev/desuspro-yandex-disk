<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'created_by',
        'name',
        'task_type_id',
        'article_id',
        'status',
        'priority',
        'ownership',
        'assignee_id',
        'public_link',
        'folder_created',
        'highlighted',
        'comment',
        'size',
        'source_files',
    ];

    protected $casts = [
        'highlighted' => 'boolean',
        'folder_created' => 'boolean',
        'size' => 'integer',
        'source_files' => 'array',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Alias for createdBy to maintain backward compatibility
    public function creator(): BelongsTo
    {
        return $this->createdBy();
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function thumbnails(): HasMany
    {
        return $this->hasMany(TaskFileThumbnail::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Task $task) {
            // Remove thumbnail files from storage; rows will be deleted via FK cascade
            foreach ($task->thumbnails as $thumb) {
                if ($thumb->thumbnail_path) {
                    try { Storage::disk('public')->delete($thumb->thumbnail_path); } catch (\Throwable $e) { /* noop */ }
                }
            }
        });
    }
}
