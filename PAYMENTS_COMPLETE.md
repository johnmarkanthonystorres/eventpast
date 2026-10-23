# Paid Tickets: Stripe Connect

Buyers pay the organizer's own Stripe account through hosted Stripe Checkout (₱ by default).
The platform fee (5% + 0.50 free, 2.5% pro, 1.5% business, 0 enterprise) is taken from each sale automatically.

## Flow
1. Admin opens **Payments** and connects a Stripe account (Express onboarding). Paid tickets stay disabled until Stripe reports `charges_enabled`.
2. Admin sets a ticket price above 0 in **Manage tickets**.
3. Buyer registers, the seat is **held** (`pending_payment`) and they are sent to Stripe Checkout.
4. Stripe calls `POST /api/webhooks/stripe`; on `checkout.session.completed` the registration becomes `confirmed`, the order/payment are recorded and the ticket email is sent.
5. If they never pay, `checkout.session.expired` (or the 10-minute `payments:release-stale` sweep) frees the seat and ticket count.

## Setup
```
STRIPE_SECRET=sk_live_or_test_...
STRIPE_WEBHOOK_SECRET=whsec_...           # platform endpoint
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_...   # connect endpoint
STRIPE_CURRENCY=php
```
In the Stripe dashboard, enable **Connect** and add two webhook endpoints, both pointing to `https://YOUR-DOMAIN/api/webhooks/stripe`:
- Account endpoint: `checkout.session.completed`, `checkout.session.expired`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`
- Connect endpoint ("events on connected accounts"): `account.updated`

Local testing: `stripe listen --forward-to localhost:8000/api/webhooks/stripe`, then `php artisan migrate`.

## Safety
- Webhook signature + 5-minute replay window verified (HMAC-SHA256). Replays are idempotent.
- A payment only confirms a registration if Stripe's amount **and** currency match the order.
- A paid waitlisted registration is never auto-promoted without payment.
- Stripe account ids are never serialized to the browser.

## Not built yet
- Refunds and cancelling a paid registration.
- Paying after a waitlist promotion (paid + full events just waitlist for now).
- Subscription billing for plans.
- The fixed fee (0.50) is in the charge currency, so it is ₱0.50 on PHP.
- The Stripe SDK is not used: calls go through `App\Services\StripeGateway` (REST), so it can be swapped later.

## Confirm before launch
Stripe availability for **Philippine** connected accounts and **PHP** charges depends on your Stripe account and Connect settings. I could not verify this from here, so check the Connect supported-countries page first.
