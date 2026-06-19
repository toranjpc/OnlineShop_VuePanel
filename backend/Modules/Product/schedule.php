<?php

use Modules\Product\Jobs\SyncBijacsJob;
use Modules\Product\Jobs\CacheBijacsRedisJob;
use Modules\Product\Jobs\SyncBijacsMeiliJob;
use Modules\Product\Jobs\SyncProductToDatabaseJob;
use Modules\Product\Jobs\FactorRequestJob;

// $schedule->job(new SyncBijacsJob)->everyFiveMinutes();
// $schedule->job(new SyncBijacsJob)->everyMinute();

// $schedule->job(new SyncBijacsMeiliJob)->everyFiveMinutes();
// $schedule->job(new SyncBijacsMeiliJob)->everyMinute();

// $schedule->job(new CacheBijacsRedisJob)->everyFiveMinutes()->withoutOverlapping();
// $schedule->job(new CacheBijacsRedisJob)->everyMinute();

// $schedule->job(new SyncProductToDatabaseJob)->everyFiveMinutes()->withoutOverlapping();
// $schedule->job(new SyncProductToDatabaseJob)->everyMinute()->withoutOverlapping();

// زنجیره‌ای: هر اجرا ۳۰ دقیقه بعد از اتمام قبلی schedule می‌شود
// $schedule->call(fn () => FactorRequestJob::bootstrapIfNeeded())
//     ->everyMinute()
//     ->name('factor-request-bootstrap')
//     ->withoutOverlapping();
