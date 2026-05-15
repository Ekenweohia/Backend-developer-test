<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'check_interval',
        'threshold',
        'status',
        'consecutive_failures',
        'last_checked_at',
        'ssl_expires_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
    ];

    public function checkHistories()
    {
        return $this->hasMany(CheckHistory::class);
    }

    public function getUptimePercentageAttribute()
    {
        $total = $this->checkHistories()->count();
        if ($total === 0) {
            return null;
        }

        $upCount = $this->checkHistories()->where('is_up', true)->count();

        return round(($upCount / $total) * 100, 2);
    }
}
