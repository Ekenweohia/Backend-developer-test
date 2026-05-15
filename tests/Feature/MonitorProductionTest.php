<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\CheckHistory;
use App\Services\MonitorService;
use App\Mail\MonitorStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonitorProductionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api_key' => 'prod_secret_123']);
    }

    /**
     * SECURITY: Verify API Key Protection
     */
    public function test_api_requires_valid_key()
    {
        // No Key
        $this->getJson('/api/monitors')->assertStatus(401);

        // Invalid Key
        $this->getJson('/api/monitors', ['X-API-KEY' => 'wrong'])
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized. Invalid API Key.']);

        // Valid Key
        $this->getJson('/api/monitors', ['X-API-KEY' => 'prod_secret_123'])
            ->assertStatus(200);
    }

    /**
     * LOGIC: Threshold Enforcement (Consecutive Failures)
     */
    public function test_status_only_changes_after_reaching_threshold()
    {
        Mail::fake();
        Http::fake(['https://fail.com' => Http::response('Error', 500)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://fail.com',
            'threshold' => 3,
            'status' => 'up',
            'consecutive_failures' => 0
        ]);

        $service = new MonitorService();

        // 1st Failure
        $service->performCheck($monitor);
        $this->assertEquals('up', $monitor->fresh()->status);
        $this->assertEquals(1, $monitor->fresh()->consecutive_failures);

        // 2nd Failure
        $service->performCheck($monitor->fresh());
        $this->assertEquals('up', $monitor->fresh()->status);
        $this->assertEquals(2, $monitor->fresh()->consecutive_failures);

        // 3rd Failure -> SHOULD BE DOWN
        $service->performCheck($monitor->fresh());
        $this->assertEquals('down', $monitor->fresh()->status);
        $this->assertEquals(3, $monitor->fresh()->consecutive_failures);
        
        Mail::assertSent(MonitorStatusChanged::class, 1);
    }

    /**
     * LOGIC: Recovery Reset
     */
    public function test_single_success_resets_consecutive_failures()
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'threshold' => 3,
            'status' => 'up',
            'consecutive_failures' => 2
        ]);

        Http::fake(['https://example.com' => Http::response('OK', 200)]);
        
        (new MonitorService())->performCheck($monitor);

        $this->assertEquals(0, $monitor->fresh()->consecutive_failures);
        $this->assertEquals('up', $monitor->fresh()->status);
    }

    /**
     * NOTIFICATIONS: Alert Fatigue Prevention
     */
    public function test_notifications_only_sent_on_transition()
    {
        Mail::fake();
        Http::fake(['https://down.com' => Http::response('Error', 500)]);

        $monitor = Monitor::factory()->create([
            'url' => 'https://down.com',
            'threshold' => 1,
            'status' => 'down',
            'consecutive_failures' => 1
        ]);

        $service = new MonitorService();

        // Site is already down. Check again.
        $service->performCheck($monitor);

        // Should still be down, but NO NEW EMAIL should be sent
        $this->assertEquals('down', $monitor->fresh()->status);
        Mail::assertNothingSent();
    }

    /**
     * SCALABILITY: Queue Dispatching
     */
    public function test_scheduler_dispatches_correct_number_of_jobs()
    {
        Queue::fake();
        
        Monitor::factory()->count(5)->create([
            'check_interval' => 1,
            'last_checked_at' => now()->subMinutes(2)
        ]);

        $this->artisan('monitors:check');

        Queue::assertPushed(\App\Jobs\PerformCheckJob::class, 5);
    }

    /**
     * DATA INTEGRITY: Uptime Calculation accuracy
     */
    public function test_uptime_percentage_calculation()
    {
        $monitor = Monitor::factory()->create();
        
        // 3 Up, 1 Down = 75%
        CheckHistory::factory()->create(['monitor_id' => $monitor->id, 'is_up' => true]);
        CheckHistory::factory()->create(['monitor_id' => $monitor->id, 'is_up' => true]);
        CheckHistory::factory()->create(['monitor_id' => $monitor->id, 'is_up' => true]);
        CheckHistory::factory()->create(['monitor_id' => $monitor->id, 'is_up' => false]);

        $this->assertEquals(75.0, $monitor->uptime_percentage);
    }
}
