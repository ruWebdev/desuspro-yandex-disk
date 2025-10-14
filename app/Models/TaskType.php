<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
