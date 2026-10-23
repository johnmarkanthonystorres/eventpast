# Scheduled Event Reminders

Emails confirmed registrants **7 days**, **1 day** and **2 hours** before an event.

## How it works
- `php artisan reminders:send` runs every 15 minutes (`app/Console/Kernel.php`).
- Only **published** events and **confirmed**, not-yet-checked-in registrations are considered.
- Only the tightest window an event is currently inside is sent, so someone who
  registers the day before gets the 1-day reminder, not a stale "one week" one.
- `reminder_logs` has a unique `(registration_id, type)` key, so a reminder is never
  sent twice, even if runs overlap. A failed send releases its slot and retries next run.
- `start_at` is stored as wall-clock time in the event's timezone, so timing is
  computed with `Event::startsAtInstant()`, not as UTC.

## Files
- `app/Console/Commands/SendEventReminders.php`
- `app/Notifications/EventReminder.php`
- `app/Models/ReminderLog.php`
- `database/migrations/2026_10_23_120000_create_reminder_logs_table.php`
- `tests/Feature/EventRemindersTest.php` (12 tests)

## Deploy
```bash
php artisan migrate
# production needs the Laravel scheduler running:
* * * * * cd /path/to/eventpast && php artisan schedule:run >> /dev/null 2>&1
# and a queue worker, since notifications are queued (QUEUE_CONNECTION != sync):
php artisan queue:work
```
Preview without sending: `php artisan reminders:send --dry-run`

## Known limits
- Reminders are not per-tenant configurable yet (fixed 7d / 1d / 2h).
- No unsubscribe link yet.
- Events created inside a window send that window's reminder on the next run.
