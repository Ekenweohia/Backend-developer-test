<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitors:check')->everyMinute();
