<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\MonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PerformCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(public Monitor $monitor)
    {
    }

    public function handle(MonitorService $service): void
    {
        $service->performCheck($this->monitor);
    }
}
