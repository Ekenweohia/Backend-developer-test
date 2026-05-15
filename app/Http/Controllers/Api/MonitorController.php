<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Http\Requests\StoreMonitorRequest;
use App\Http\Resources\MonitorResource;
use App\Http\Resources\CheckHistoryResource;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    /**
     * List all monitors.
     */
    public function index()
    {
        return MonitorResource::collection(Monitor::all());
    }

    /**
     * Register a new monitor.
     */
    public function store(StoreMonitorRequest $request)
    {
        $monitor = Monitor::create([
            'url' => $request->url,
            'check_interval' => $request->check_interval ?? 5,
            'threshold' => $request->threshold ?? 3,
            'status' => 'pending',
        ]);

        return new MonitorResource($monitor);
    }

    /**
     * Fetch history for a specific monitor.
     */
    public function history(int $id, Request $request)
    {
        $monitor = Monitor::findOrFail($id);

        $perPage = $request->query('per_page', 15);
        $perPage = min((int) $perPage, 100);

        $history = $monitor->checkHistories()
            ->orderBy('checked_at', 'desc')
            ->paginate($perPage);

        return CheckHistoryResource::collection($history);
    }

    /**
     * Get statistics for a monitor. (Additional Feature)
     */
    public function stats(int $id)
    {
        $monitor = Monitor::findOrFail($id);
        
        $stats = [
            'uptime_percentage' => $monitor->uptime_percentage,
            'avg_response_time_ms' => round($monitor->checkHistories()->avg('response_time_ms'), 2),
            'total_checks' => $monitor->checkHistories()->count(),
            'last_failure' => $monitor->checkHistories()->where('is_up', false)->latest('checked_at')->first()?->checked_at?->format('Y-m-d\TH:i:s.u\Z'),
            'ssl_expiration' => $monitor->ssl_expires_at?->format('Y-m-d\TH:i:s.u\Z'),
        ];

        return response()->json(['data' => $stats]);
    }
}
