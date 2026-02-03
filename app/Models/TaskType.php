<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TaskType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prefix',
        'create_empty_folder',
    ];

    protected $casts = [
        'create_empty_folder' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Clear task types cache when a task type is created, updated, or deleted
        static::saved(function () {
            Cache::forget('task_types_list');
        });

        static::deleted(function () {
            Cache::forget('task_types_list');
        });
    }
}
