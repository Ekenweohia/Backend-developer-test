<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'check_interval' => $this->check_interval,
            'threshold' => $this->threshold,
            'status' => $this->status,
            'last_checked_at' => $this->last_checked_at?->format('Y-m-d\TH:i:s.u\Z'),
            'ssl_expires_at' => $this->ssl_expires_at?->format('Y-m-d\TH:i:s.u\Z'),
            'uptime_percentage' => $this->uptime_percentage,
            'created_at' => $this->created_at->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }
}
