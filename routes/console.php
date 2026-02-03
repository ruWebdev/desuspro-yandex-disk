<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic cleanup of old thumbnails (daily at 3:00 AM)
Schedule::command('thumbnails:cleanup')->dailyAt('03:00');
// Ensure Yandex.Disk folders for tasks without folders (every 5 minutes)
Schedule::command('tasks:ensure-folders')->everyFiveMinutes();
