<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateApiKey extends Command
{
    protected $signature = 'app:generate-api-key';
    protected $description = 'Generate a secure API key for the monitor';

    public function handle()
    {
        $key = Str::random(64);
        $this->info("Generated API Key: {$key}");
        $this->warn("Please add this to your .env as: APP_API_KEY={$key}");
    }
}
