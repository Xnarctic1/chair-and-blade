<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping(5);
Schedule::command('appointments:send-emails')->everyMinute()->withoutOverlapping(5);
