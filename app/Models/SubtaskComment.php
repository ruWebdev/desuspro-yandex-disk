<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubtaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subtask_id',
        'user_id',
        'content',
    ];

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
