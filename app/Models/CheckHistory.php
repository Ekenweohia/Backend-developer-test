<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckHistory extends Model
{
    /** @use HasFactory<\Database\Factories\CheckHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'status_code',
        'response_time_ms',
        'is_up',
        'checked_at',
    ];

    protected $casts = [
        'is_up' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function monitor()
    {
        return $this->belongsTo(Monitor::class);
    }
}
