# Uptime Monitor API

This is a production-ready uptime monitoring system built with Laravel 13 and PHP 8.4.

---

## 🏗️ Technical Rationale (Why this approach?)

When building a monitor, you have to plan for scale. Here’s why I chose this specific architecture:

### 1. Asynchronous Queueing (Scalability)
**Approach**: I used Laravel Queues to handle the actual HTTP checks.
**Why**: If you have 1,000 sites to check every minute, a sequential loop will take too long and eventually fail. By using jobs, we can spin up multiple queue workers to process checks in parallel. It turns a bottleneck into a horizontally scalable system.

### 2. Service Layer Pattern (Maintainability)
**Approach**: All monitoring logic is inside `MonitorService`.
**Why**: Controllers should only handle requests and responses. By moving the logic to a service, we make the code testable in isolation and reusable. For example, we can trigger a check from a Web UI, an API, or a CLI command using the exact same code.

### 3. Event-Driven Notifications (Decoupling)
**Approach**: Status changes fire a `MonitorStatusChanged` event.
**Why**: Monitoring is the "hot path"—it needs to be fast. Sending an email is slow. By using events, we "fire and forget" the notification. If the mail server is slow or down, it doesn't block the next uptime check from starting.

---

## 📡 API Reference & Testing

All endpoints require the `X-API-KEY` header.

### 1. Register a Monitor
**POST** `/api/monitors`
- **Body**:
  ```json
  {
    "url": "https://example.com",
    "check_interval": 5,
    "threshold": 3
  }
  ```
- **Success (201)**: Returns the new monitor object with `status: "pending"`.
- **Error (422)**: If URL is missing, invalid, or already exists.

### 2. List All Monitors
**GET** `/api/monitors`
- **Success (200)**:
  ```json
  {
    "data": [
      {
        "id": 1,
        "url": "https://example.com",
        "status": "up",
        "uptime_percentage": 99.5,
        "last_checked_at": "2026-05-15T10:00:00.000000Z"
      }
    ]
  }
  ```

### 3. Check History
**GET** `/api/monitors/{id}/history`
- **Query Params**: `page`, `per_page`
- **Success (200)**: Returns a list of every check. If a check failed due to timeout, `status_code` will be `0` and `response_time_ms` will be `null`.
- **Error (404)**: `{"message": "Monitor not found."}`

---

## 🧪 How to Test

### Automated Tests
I’ve written 15 functional tests covering security, threshold logic, and alerts.
```bash
php artisan test
```

### Manual Testing with cURL
1. **Generate your key**: `php artisan app:generate-api-key`
2. **Add to .env**: `APP_API_KEY=your_key`
3. **Run the call**:
   ```bash
   curl -X POST http://localhost:8000/api/monitors \
        -H "X-API-KEY: your_key" \
        -H "Content-Type: application/json" \
        -d '{"url": "https://google.com"}'
   ```
4. **Trigger Checks**:
   Open two terminals:
   - Term 1: `php artisan schedule:work` (dispatches checks)
   - Term 2: `php artisan queue:work` (processes the checks)
