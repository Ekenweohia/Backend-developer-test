<?php

namespace App\Console\Commands;

use App\Models\Monitor;
use App\Models\CheckHistory;
use App\Mail\MonitorStatusChanged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;

class CheckMonitors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all monitors and update their status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $monitors = Monitor::all();

        foreach ($monitors as $monitor) {
            if ($monitor->last_checked_at && $monitor->last_checked_at->addMinutes($monitor->check_interval)->isFuture()) {
                continue;
            }

            \App\Jobs\PerformCheckJob::dispatch($monitor);
            $this->info("Dispatched check for {$monitor->url}");
        }

        $this->info('Monitoring tasks dispatched successfully.');
    }
}
