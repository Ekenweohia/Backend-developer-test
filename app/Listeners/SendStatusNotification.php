<?php

namespace App\Listeners;

use App\Events\MonitorStatusChanged;
use App\Mail\MonitorStatusChanged as MonitorStatusChangedMail;
use Illuminate\Support\Facades\Mail;

class SendStatusNotification
{
    public function handle(MonitorStatusChanged $event): void
    {
        Mail::to(config('mail.from.address'))
            ->send(new MonitorStatusChangedMail($event->monitor));
    }
}
