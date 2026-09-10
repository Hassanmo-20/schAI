<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders. Hourly rather than daily so a task created late still
// gets a reminder before it is due; the command itself is idempotent per task.
Schedule::command('schai:notify-deadlines')
    ->hourly()
    ->withoutOverlapping();
