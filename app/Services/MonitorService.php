<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\CheckHistory;
use App\Events\MonitorStatusChanged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class MonitorService
{
    /**
     * Perform an uptime check on a monitor.
     */
    public function performCheck(Monitor $monitor): void
    {
        $startTime = microtime(true);
        $isUp = false;
        $statusCode = 0;
        $sslExpiresAt = $monitor->ssl_expires_at;

        try {
            // Use a 10s timeout so we don't hang the worker
            $response = Http::timeout(10)->get($monitor->url);
            $statusCode = $response->status();
            $isUp = $response->successful();
            $responseTime = round((microtime(true) - $startTime) * 1000);
            
            // Only try to grab SSL info if it's an https link
            if (str_starts_with($monitor->url, 'https://')) {
                $sslExpiresAt = $this->getSslExpiry($monitor->url);
            }
        } catch (\Exception $e) {
            // If the request fails completely (timeout/dns/etc), log it and set status to 0
            Log::error("Monitor check failed for {$monitor->url}: " . $e->getMessage());
            $statusCode = 0;
            $isUp = false;
            $responseTime = null;
        }

        // Wrap the update in a transaction so we don't end up with partial data
        \DB::transaction(function () use ($monitor, $statusCode, $responseTime, $isUp, $sslExpiresAt) {
            CheckHistory::create([
                'monitor_id' => $monitor->id,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'is_up' => $isUp,
                'checked_at' => now(),
            ]);

            $oldStatus = $monitor->status;
            $newStatus = $this->calculateNewStatus($monitor, $isUp);

            $monitor->update([
                'status' => $newStatus,
                'consecutive_failures' => $isUp ? 0 : ($monitor->consecutive_failures + 1),
                'last_checked_at' => now(),
                'ssl_expires_at' => $sslExpiresAt,
            ]);

            if ($oldStatus !== $newStatus && $oldStatus !== 'pending') {
                event(new MonitorStatusChanged($monitor, $oldStatus, $newStatus));
            }
        });
    }

    private function calculateNewStatus(Monitor $monitor, bool $isUp): string
    {
        if ($isUp) {
            return 'up';
        }

        if (($monitor->consecutive_failures + 1) >= $monitor->threshold) {
            return 'down';
        }

        return $monitor->status;
    }

    private function getSslExpiry(string $url): ?Carbon
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            $g = stream_context_create(["ssl" => ["capture_peer_cert" => true]]);
            $r = fopen("https://$host", "rb", false, $g);
            $cont = stream_context_get_params($r);
            $cert = openssl_x509_parse($cont["options"]["ssl"]["peer_certificate"]);
            
            return Carbon::createFromTimestamp($cert['validTo_time_t']);
        } catch (\Exception $e) {
            return null;
        }
    }
}
