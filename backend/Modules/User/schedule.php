<?php

use Modules\User\Jobs\MarkOfflineUsersJob;

$schedule->job(new MarkOfflineUsersJob)
    ->everyMinute()
    ->withoutOverlapping();
