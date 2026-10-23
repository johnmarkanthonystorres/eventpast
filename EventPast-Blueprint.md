# EventPast Blueprint

Oct 21, 2026

EventPast is a multi-tenant SaaS where organizers publish events and sell or manage registrations. This blueprint covers scope, architecture, data model, pricing and a growth plan to increase participants per event. The backend is already largely built, so this version marks what exists and focuses the plan on the gaps that limit participant growth. Strapi handles content only.

## Current build status

The core event loop works. What is missing is mostly what brings participants in: a public registration experience, referrals, reminders and payments.

| Area | Status |
| --- | --- |
| Organizations, multi-tenancy, Free/Pro/Business plans, plan enforcement | Built |
| Event management API, Event and Ticket controllers, routes | Built |
| Registration with QR codes, check-in tracking | Built |
| Waitlist with auto-promotion | Built |
| Email notifications: confirmation, waitlist, promotion, cancellation (HTML, embedded QR) | Built |
| MailDev for development, production SMTP ready | Built |
| Analytics and CSV export, admin panel | Built |
| **Strapi CMS (content management)** | **In Progress** |
| StrapiService (Laravel API integration) | In Progress |
| Public event page and participant-facing registration UI | Not confirmed |
| Referral and invite links, share cards, add-to-calendar | Missing |
| Scheduled reminders (7 days, 1 day, 2 hours) | Missing |
| Source tracking (UTM, per-channel attribution) | Missing |
| Paid tickets, Stripe payouts, subscription billing | Missing |
| Public directory, search, SEO event pages | Missing |

## Product scope

The MVP covers the full loop of publish, register, check in. Growth features come only after organizers can run a real event end to end.

| Module | What it does | Phase |
| --- | --- | --- |
| Organizer workspace | Sign-up, team members, roles, branding (logo, colors, custom subdomain) | MVP |
| Event builder | Create event, schedule, venue or online link, ticket types, capacity | MVP |
| Public event page | SEO-friendly page with share buttons and a one-click register button | MVP |
| Registration and tickets | Forms, free and paid tickets, QR code per attendee, confirmation emails | MVP |
| Check-in | QR scan on a phone, live attendance count | MVP |
| Payments | Stripe (cards), local wallets and bank options per market, payouts to organizers | MVP |
| Reminders and campaigns | Scheduled emails and SMS to registrants, announcement blasts | Growth |
| Referral and invite tools | Invite links, discount codes, ticket-sharing rewards | Growth |
| Discovery marketplace | Public EventPast directory with search, categories and city filters | Growth |
| Analytics | Registrations by source, conversion, no-show rate | Growth |
| Integrations | Zoom/Meet, Google Calendar, Zapier, Facebook and TikTok pixels | Growth |
| Public API and webhooks | Let customers connect their own tools | Scale |
| White-label and enterprise | SSO, custom domain, audit logs | Scale |

## Architecture

Laravel is the core application and the system of record for tenants, events and registrations. Strapi manages editorial content only: marketing pages, blog, help center and featured-event collections. Laravel reads Strapi over its REST API and caches the responses.

| Layer | Technology | Role |
| --- | --- | --- |
| Web app and API | Laravel 12, Sanctum, Livewire or Inertia (Vue/React) | Organizer dashboard, registration flow, business logic |
| Multi-tenancy | Single database with `tenant_id` (or stancl/tenancy) | Isolates each organizer's data, supports subdomains |
| Content CMS | Strapi (Node.js, own Postgres DB) | Landing pages, blog, SEO content, help articles, curated lists |
| Database | PostgreSQL or MySQL | Events, tickets, registrations, payments |
| Cache and queues | Redis, Laravel Horizon | Page cache, email/SMS jobs, webhook handling |
| Payments | Stripe Connect (plus local gateway per market) | Ticket sales, organizer payouts, platform fee |
| Email and SMS | Postmark or SES, Twilio or a local SMS provider | Confirmations, reminders, campaigns |
| Search | Meilisearch or Typesense | Fast event discovery for the marketplace |
| Storage and CDN | S3-compatible bucket, Cloudflare | Images, ticket PDFs, static assets |
| Hosting | Containers on a managed platform (Laravel Cloud, Fly.io or AWS) | Separate services for Laravel and Strapi |

### Strapi CMS Implementation Details

**Content Types in Strapi:**
- Landing Pages (hero, features, testimonials, CTAs)
- Blog Posts (title, slug, content, author, publish date, SEO metadata)
- Help Articles (category, title, content, related articles)
- Featured Events (curated event IDs from Laravel, display order, featured image)
- Success Stories (organizer testimonials, metrics, logos)
- Event Playbooks (guides like "How to fill your first 100 seats")

**Integration Rules:**

1. Laravel owns all users and attendees. Strapi has only editor accounts.
2. Laravel calls Strapi with a read-only API token and caches responses for 5 to 15 minutes.
3. Strapi sends a webhook to Laravel on publish so the cache clears immediately.
4. Event data never lives in Strapi, to keep one source of truth.
5. Strapi runs as a separate service (port 1337 locally, separate container in production).
6. Shared assets (images, PDFs) stored in S3, accessible by both systems.

**API Endpoints:**
- `GET /api/landing-pages/:slug` - Fetch landing page content
- `GET /api/blog-posts` - List blog posts with pagination
- `GET /api/blog-posts/:slug` - Single blog post
- `GET /api/help-articles` - Help center articles by category
- `GET /api/featured-events` - Curated event list
- `POST /api/webhooks/content-published` - Webhook to Laravel for cache clearing

## Data model

Laravel owns every entity below. Each row carries a `tenant_id` except platform-level tables.

| Entity | Key fields | Notes |
| --- | --- | --- |
| Tenant | name, subdomain, plan, brand settings, payout account | One per organizer organization |
| User | name, email, role (owner, admin, staff), tenant_id | Organizer team members |
| Event | title, slug, start/end, timezone, venue or link, status, capacity | Draft, published, ended |
| TicketType | event_id, name, price, quantity, sales window | Free, paid, early-bird, group |
| Attendee | name, email, phone, marketing consent | Deduplicated per tenant by email |
| Registration | event_id, attendee_id, ticket_type_id, status, qr_token, source | `source` stores the referral or campaign for attribution |
| Order and Payment | registration ids, amount, fee, Stripe id, status | Platform fee tracked per order |
| Referral | registration_id, code, clicks, conversions | Powers invite rewards |
| Campaign | event_id, channel, audience, schedule, stats | Email and SMS sends |
| CheckIn | registration_id, scanned_by, time | Real-time attendance |

## Pricing

Pricing mixes a flat subscription with a small fee on paid tickets, so the platform earns more as organizers grow. All figures are starting hypotheses in USD to test, not benchmarks.

| Plan | Price | Events | Participants per event | Ticket fee | Key features |
| --- | --- | --- | --- | --- | --- |
| Free | $0 | 2 active | 50 | 5% + $0.50 | Event page, registration, QR check-in |
| Pro | $29 per month | 20 active | 500 | 2.5% | Reminders, referral tools, analytics, custom subdomain |
| Business | $99 per month | Unlimited | 5,000 | 1.5% | Integrations, API, team roles, priority support |
| Enterprise | Custom | Unlimited | Unlimited | Negotiated | SSO, white-label, SLA |

The free tier matters for growth: every free event page carries "Powered by EventPast" and exposes new people to the platform.

### How a subscription raises participants

The plan sets how many participants an organizer can accept per event. Upgrading is the way to grow an event past its limit.

1. Each plan has a participant cap per event: Free 50, Pro 500, Business 5,000, Enterprise unlimited.
2. When registrations reach the cap, new people go to the waitlist instead of being turned away.
3. The organizer sees a prompt such as "Your event is full and 12 people are waiting. Upgrade to Pro to open 450 more seats."
4. After upgrading, the cap rises immediately and the existing waitlist auto-promotes, with promotion emails sent.
5. If an organizer later downgrades, current registrations stay valid but new registrations stop at the lower cap.
6. Optional add-on to test: a one-time seat pack for a single event (for example +200 participants), for organizers who do not want a monthly plan.

## QR ticket verification

Every registration gets one unique QR code. At the entrance, event staff scan it and the system confirms whether the person is a legitimate participant of that event.

1. When a registration is confirmed, Laravel generates a random, unguessable token (at least 128 bits) and stores it as `qr_token` on the registration.
2. The QR code carries only that token, and is embedded in the confirmation email and shown on the participant's ticket page.
3. Staff log in to the organizer app, open the event's scanner and scan with a phone camera.
4. The app sends the token to the check-in endpoint. Laravel checks that the token exists, belongs to this event and tenant, and has a valid status.
5. The first valid scan writes a CheckIn record (who scanned, when) and shows a green result. Any later scan of the same code shows red with the original check-in time.

| Scan result | Screen | Reason |
| --- | --- | --- |
| Valid | Green: name, ticket type | Confirmed, paid, first scan |
| Already used | Red: time and scanner of first check-in | Duplicate or shared screenshot |
| Wrong event | Red | Token belongs to a different event or organization |
| Cancelled or refunded | Red | Registration is no longer active |
| Payment pending | Amber | Paid ticket not yet settled |
| Waitlisted | Red | Not promoted, so no seat |
| Unknown code | Red | Token not found, likely forged |

Safeguards:

- Only staff and admins of the event's organization can scan, and scans are rate limited and logged.
- Manual lookup by name or email handles guests who lost their phone, with the same one-entry rule.
- Optional offline mode: the scanner downloads the event's valid tokens before doors open and syncs scans when back online.
- Organizers can reissue a ticket, which invalidates the old token, if a QR code is shared or leaked.
- Live attendance count and no-show rate feed the analytics already built.

## Participant growth plan

The goal is to raise registrations per event and bring in new participants who later become organizers. Growth comes from four levers, each built into the product so it works without extra marketing spend. Targets are hypotheses to validate against your first 20 events.

| Lever | Tactics | Metric | Target |
| --- | --- | --- | --- |
| 1. Make registering effortless | One-page checkout, guest registration without an account, Apple/Google Pay, autofill, mobile-first form, under 60 seconds to finish | Page-to-registration conversion | 25% or higher |
| 2. Turn every participant into a promoter | Share-after-register screen, personal invite link, "bring a friend" discount, reward for 3 successful invites, add-to-calendar and social share cards | Invites per registrant, referral share of registrations | 0.5 invites each, 20% of registrations |
| 3. Give organizers the tools to promote | Email and SMS campaigns, WhatsApp and Messenger share links, UTM tracking per channel, embeddable register widget for their own site, early-bird and last-chance countdowns | Registrations by source, campaign open rate | 40% of registrations from organizer campaigns |
| 4. Get discovered on EventPast | Public directory, search by city and category, "events near you", personalized email digest, SEO event pages with schema markup, featured events curated in Strapi | Organic and directory registrations | 15% of registrations by month 6 |
| 5. Reduce no-shows | Reminder sequence at 7 days, 1 day and 2 hours, calendar sync, easy cancel to release seats, waitlist that auto-fills | Show-up rate | 70% or higher (free events) |
| 6. Bring people back | Attendee profile with past events, "organizer you attended" follow prompts, post-event survey with the next event link | Repeat attendance rate | 30% within 90 days |

### Growth loops

1. **Participant loop:** Participant registers, shares an invite link, a friend registers, the friend sees EventPast and later creates their own event.
2. **Organizer loop:** Organizer runs a successful event, the analytics show registrations by channel, the organizer upgrades to unlock campaigns and referral tools.
3. **Search loop:** Each published event becomes a search-indexed page, bringing organic visitors who register and enter the directory.

### Acquisition for the first organizers

- Recruit 20 pilot organizers (communities, schools, churches, meetups, small conferences) with free Pro for 3 months in exchange for feedback and case studies.
- Partner with 3 to 5 community networks or venues that run recurring events.
- Offer migration help from Eventbrite, Google Forms and spreadsheet registrations.
- Publish playbooks in Strapi, such as "How to fill your first 100 seats", to bring in organizers through search.

## 90-day roadmap

The core event loop already exists, so the 90 days go to the gaps in order of their effect on participant count.

1. **Week 0, Strapi CMS setup:** Install Strapi, configure content types (Landing Pages, Blog, Help Articles, Featured Events), integrate with Laravel via StrapiService, implement cache layer.
2. **Weeks 1 to 2, public registration:** Public event page, one-page registration form, share buttons and cards, add-to-calendar.
3. **Weeks 3 to 4, referrals and tracking:** Invite links per registrant, reward rules (for example 3 invites), UTM source on every registration, analytics by source.
4. **Weeks 5 to 6, reminders:** Scheduled sequence at 7 days, 1 day and 2 hours, using the existing email templates, plus optional SMS.
5. **Weeks 7 to 9, payments:** Stripe Connect for paid tickets, platform fee, payouts, subscription billing for Free/Pro/Business.
6. **Weeks 10 to 13, discovery and launch:** Public directory with search, SEO event pages, Strapi blog and playbooks, onboard 20 pilot organizers.

Gate to move from pilot to public launch: at least 10 pilot events completed, registration conversion above 20%, and no critical bugs in check-in or payments.

## KPIs

Track one north-star metric, registrations per event, plus the funnel behind it. Targets are starting hypotheses.

| Metric | Definition | Month 3 target | Month 6 target |
| --- | --- | --- | --- |
| Registrations per event | Average confirmed registrations on published events | 60 | 120 |
| Page-to-registration conversion | Registrations divided by event page visits | 20% | 25% |
| Referral share | Registrations that came from invite links | 10% | 20% |
| Show-up rate | Checked-in divided by registered | 60% | 70% |
| Active organizers | Organizers with at least one published event in 30 days | 20 | 100 |
| Free to paid upgrade | Free organizers who move to Pro or Business | 5% | 8% |
| Monthly recurring revenue | Subscriptions plus ticket fees | $1,000 | $6,000 |
| Repeat attendance | Participants who register for a second event in 90 days | 15% | 30% |

## Open questions

- [ ] Is there already a participant-facing frontend, and in what stack (Blade, Vue, React, Next.js)?
- [ ] Which country launches first, and which payment methods and currency does it need?
- [ ] Who are the first target organizers: communities, schools, corporate teams or conferences?
- [ ] Is the growth goal attendees per event, or total users on the platform?
- [ ] What launch budget is available for paid acquisition, if any?
