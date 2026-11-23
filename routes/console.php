<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CU20: las cotizaciones con la fecha de vencimiento pasada quedan VENCIDAS cada día.
Schedule::command('cotizaciones:vencer')->dailyAt('00:10');
