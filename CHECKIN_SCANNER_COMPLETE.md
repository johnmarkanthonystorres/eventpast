# Check-in Scanner

Page: `/events/{event}/check-in` (button on the event page once published).

## What it does
- Live camera QR scanning (jsQR). Prefers the rear camera, ignores the same code for 4s.
- Result banner: green = checked in, amber = already checked in (with time), red = invalid / waitlisted / cancelled / unpaid / wrong event.
- Live "X / Y checked in" progress bar.
- Attendee list with name/email search and a manual **Check in** button, for dead phones or unreadable codes.

## Fixes made along the way
- `Events/CheckIn.vue` did not exist (the button led to a broken page).
- Door list and totals counted only `confirmed`, so scanned people vanished and the count fell. They now include `checked_in`.
- Scan API required owner/admin while the page allowed any tenant user; door staff were refused. Any user in the event's tenant can now check people in (other tenants are still blocked).
- `Content/*.vue` imported an uninstalled `ziggy-js` package, which broke `vite build`. Removed; the global `route()` is used like every other page.

## Notes
- Camera access requires HTTPS or `localhost`; otherwise the page shows a message and the list still works.
- Run `npm install && npm run build` after pulling (adds `jsqr`).
- Tests: `tests/Feature/CheckInTest.php` (10).
