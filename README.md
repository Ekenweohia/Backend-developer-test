# Uptime Monitor API

This is a simple but scalable uptime monitoring system built with Laravel 13 and PHP 8.4. It handles URL registration, periodic status checks via background jobs, and sends email alerts when things go sideways.

---

## How it works (The Architecture)

I went with a queue-based approach because looping through URLs in a single process is a recipe for disaster once you have more than a handful of sites.

- **The Checker**: There's a custom artisan command `monitors:check` that runs every minute via the scheduler. It just looks for what needs checking and throws a job onto the queue.
- **Background Jobs**: Each check happens in its own `PerformCheckJob`. This keeps things fast and allows us to run multiple checks in parallel.
- **Thresholds**: We don't want to spam the user if a site blips for a second. The system waits until a site has failed X times in a row before officially marking it as "down."
- **Events**: I decoupled the notifications. When a status changes, an event fires. This way, if we want to add Slack or SMS alerts later, we just add a new listener.

## Security

I added a simple `X-API-KEY` middleware. It's not OAuth, but it's perfect for a private API like this.
You can generate a key using:
```bash
php artisan app:generate-api-key
```
Then just drop it into your `.env` as `APP_API_KEY`.

## API Endpoints

- `POST /api/monitors`: Register a site. Defaults to 5-min checks and 3-fail threshold.
- `GET /api/monitors`: Current status of everything.
- `GET /api/monitors/{id}/history`: Paginated check history.
- `GET /api/monitors/{id}/stats`: Some extra metrics (latency, SSL expiry, etc).

## Setup

1. `composer install`
2. `cp .env.example .env` && `php artisan key:generate`
3. Set up your MySQL credentials in `.env`.
4. `php artisan migrate`
5. `php artisan schedule:work` (to start the heartbeat)
6. `php artisan queue:work` (to actually run the checks)

## Testing

I've covered the core logic and API endpoints with feature tests. You can run them with:
```bash
php artisan test
```
Check `MonitorProductionTest.php` if you want to see how the threshold and notification logic is handled.
