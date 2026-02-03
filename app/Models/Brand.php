<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    protected static function booted(): void
    {
        // Clear brands cache when a brand is created, updated, or deleted
        static::saved(function () {
            Cache::forget('brands_list');
            Cache::forget('brands_list_full');
        });

        static::deleted(function () {
            Cache::forget('brands_list');
            Cache::forget('brands_list_full');
        });
    }
}
