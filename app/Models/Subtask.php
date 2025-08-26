<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'name',
        'status',
        'ownership',
        'assignee_id',
        'public_link',
        'highlighted',
        'comment',
        'size',
    ];

    protected $casts = [
        'highlighted' => 'boolean',
        'size' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
