<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dọn ảnh editor rác mỗi ngày lúc 03:00, tránh chạy chồng lệnh
Schedule::command('clean:editor-images')
    ->dailyAt('03:00')
    ->withoutOverlapping();

