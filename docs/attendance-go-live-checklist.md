# Attendance Module Go-Live Checklist

## Current code status
- Attendance module migrations are applied.
- Attendance routes are registered.
- Attendance automated tests are passing.
- Scheduler entries for reminders, finalization, and suspicion scan are present.

## Manual verification before go-live
- Log in as a mapped user and open the dashboard attendance widget.
- Allow location permission and verify `Punch In` works inside the geo-fence.
- Verify out-of-radius punch creates a suspicious flag instead of breaking the flow.
- Verify camera capture works and the attendance photo uploads successfully.
- Verify `Punch Out` updates worked minutes.
- Verify half-day punch in between `1:00 PM` and `4:00 PM`.
- Verify leave request submission and approval.
- Verify regularization request submission and approval.
- Verify overtime candidate appears after a long work day and HR can approve it.
- Verify payroll preview updates after approved attendance changes.
- Verify payroll freeze blocks edits for the frozen month.
- Verify suspicious queue review actions work from HR reports.

## Production checks
- Run scheduler continuously using Laravel scheduler.
- Confirm storage is writable for attendance photos and attachments.
- Confirm queue/notifications infrastructure is working if reminders rely on it.
- Verify timezone is correct for attendance windows.

## Useful commands
- `php artisan attendance:health-check`
- `php artisan attendance:rebuild-month 2026 4`
- `php artisan attendance:detect-suspicion`
- `php artisan schedule:list`
- `php artisan test --filter=Attendance`
