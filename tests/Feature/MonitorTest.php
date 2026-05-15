<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\CheckHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonitorStatusChanged;
use Tests\TestCase;

class MonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-API-KEY', config('app.api_key'));
    }

    public function test_can_register_monitor()
    {
        $response = $this->postJson('/api/monitors', [
            'url' => 'https://example.com',
            'check_interval' => 5,
            'threshold' => 3
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'url', 'check_interval', 'threshold', 'status', 'last_checked_at', 'uptime_percentage', 'created_at'
                ]
            ]);

        $this->assertDatabaseHas('monitors', [
            'url' => 'https://example.com'
        ]);
    }

    public function test_rejects_duplicate_url()
    {
        Monitor::factory()->create(['url' => 'https://example.com']);

        $response = $this->postJson('/api/monitors', [
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_list_monitors()
    {
        Monitor::factory()->count(3)->create();

        $response = $this->getJson('/api/monitors');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_get_monitor_history()
    {
        $monitor = Monitor::factory()->create();
        CheckHistory::factory()->count(20)->create(['monitor_id' => $monitor->id]);

        $response = $this->getJson("/api/monitors/{$monitor->id}/history?per_page=10");

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 20);
    }

    public function test_monitor_not_found_history()
    {
        $response = $this->getJson('/api/monitors/999/history');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Monitor not found.']);
    }

    public function test_monitoring_logic_dispatches_jobs()
    {
        \Illuminate\Support\Facades\Queue::fake();

        Monitor::factory()->create(['url' => 'https://up.com', 'status' => 'pending']);
        Monitor::factory()->create(['url' => 'https://down.com', 'status' => 'up']);

        $this->artisan('monitors:check');

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PerformCheckJob::class, 2);
    }

    public function test_perform_check_job_updates_status()
    {
        Mail::fake();
        Http::fake([
            'https://up.com' => Http::response('OK', 200),
        ]);

        $monitor = Monitor::factory()->create(['url' => 'https://up.com', 'status' => 'pending']);
        
        $job = new \App\Jobs\PerformCheckJob($monitor);
        $job->handle(new \App\Services\MonitorService());

        $this->assertEquals('up', $monitor->fresh()->status);
    }
}
