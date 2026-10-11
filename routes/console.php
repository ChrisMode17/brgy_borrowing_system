<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:reset-preparation-data', function (): void {
    $this->call('db:wipe', [
        '--force' => true,
    ]);

    Schema::dropIfExists('sessions');

    $this->call('migrate', [
        '--force' => true,
        '--seed' => true,
    ]);
})->purpose('Reset the database and recreate preparation baseline data');
