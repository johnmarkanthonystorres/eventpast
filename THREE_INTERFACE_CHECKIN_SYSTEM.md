# Three-Interface Check-in System

## Overview
The check-in system is designed with **three distinct user interfaces**, each optimized for a specific role:

1. **Admin Dashboard** - For event creators to manage and monitor
2. **Scanner Interface** - For door staff to check in attendees
3. **Participant View** - For attendees to display their QR code

---

## 1. Admin Dashboard (Event Creators)

### Purpose
Event creators need comprehensive oversight of registrations and check-ins.

### Access
- **Route:** `/events/{event}`
- **Auth:** Requires authentication and event ownership
- **Component:** `resources/js/Pages/Events/Show.vue`

### Features

#### Check-in Overview Card
- **Visual Stats:**
  - Checked In count (blue)
  - Pending count (green)
  - Check-in Rate percentage (gray)
- **Quick Access:** "Open Scanner" button
- **Recent Check-ins:** Last 5 check-ins with timestamps

#### Registrations Table
Enhanced with check-in column showing:
- ✓ Checked In (green) with checkmark icon
- Not yet (gray) for pending

#### Statistics Sidebar
- Total Registrations
- Confirmed count
- Waitlisted count
- Checked In count

### Key Computed Values
```javascript
checkedInCount = registrations.filter(r => r.check_in !== null).length
checkInRate = (checkedInCount / confirmedCount) * 100
recentCheckIns = sorted by check_in.checked_in_at DESC
```

### Code Location
- **Controller:** `app/Http/Controllers/EventController.php::show()`
- **View:** `resources/js/Pages/Events/Show.vue`
- **Data Loading:** Eager loads `registrations.checkIn`, `registrations.attendee`, `registrations.ticketType`

---

## 2. Scanner Interface (Door Staff)

### Purpose
Simple, focused interface for scanning QR codes at the entrance.

### Access
- **Route:** `/events/{event}/check-in`
- **Auth:** Requires authentication and event access
- **Component:** `resources/js/Pages/CheckIn/Scanner.vue`

### Features

#### Camera Scanner
- **Auto-start:** Camera activates on page load
- **Visual Elements:**
  - Live video feed (4:3 aspect ratio)
  - Blue scan target box overlay
  - Status indicator (Active/Paused)
- **Scan Frequency:** Every 300ms
- **Camera Mode:** Rear camera (`facingMode: 'environment'`)

#### Scan Results (Full-screen overlay)
**Success (Green):**
- ✓ Checkmark icon
- "Welcome!" message
- Attendee name
- Event-specific message

**Error (Red):**
- ✗ X icon
- Error type (Already Checked In, Invalid, etc.)
- Attendee name (if available)
- Specific error message

#### Real-time Stats
Three stat cards showing:
- Checked In (green)
- Pending (blue)
- Check-in Rate % (purple)

#### Recent Scans Log
- Last 10 scans
- Shows name, email, success/fail, and time
- Color-coded by status

#### Controls
- **Pause/Resume:** Toggle scanning
- **Back to Event:** Return to admin dashboard

#### Audio Feedback
- Success scan: High-pitched beep
- Error scan: Low-pitched buzz

### Technical Details

**QR Code Processing:**
```javascript
1. Capture video frame to canvas
2. Extract image data
3. Process with jsQR library
4. Send qr_token to backend
5. Display result overlay (3 seconds)
6. Resume scanning
```

**API Integration:**
```javascript
POST /check-in/scan
Body: { qr_token: "..." }
Response: { success, message, attendee_name, attendee_email }
```

### Code Location
- **Controller:** `app/Http/Controllers/CheckInController.php::index()`
- **View:** `resources/js/Pages/CheckIn/Scanner.vue`
- **Dependencies:** `jsqr` library for QR code reading

---

## 3. Participant View (Attendees)

### Purpose
Attendees need to display their QR code for scanning at the entrance.

### Access
- **Route:** `/my-ticket/{registration}`
- **Auth:** No authentication required (public with secret token)
- **Component:** `resources/js/Pages/Registrations/MyTicket.vue`

### Features

#### Ticket Header
- Event title
- Status badge:
  - "✓ CHECKED IN" (green) if already scanned
  - "VALID" (white) if not yet checked in

#### QR Code Display
**Before Check-in:**
- Large QR code (256x256px)
- White background with border
- Token preview (first 16 chars)
- Instructions to show at entrance

**After Check-in:**
- Success icon (large green checkmark)
- "Already Checked In" message
- Check-in timestamp
- Staff member who scanned (if available)

#### Event Details Card
Read-only display of:
- Attendee name and email
- Ticket type
- Event date/time
- Location (venue or online link)

#### Actions
- **Download Ticket (PDF):** Generate printable ticket
- **Share Ticket:** Use Web Share API or copy link

#### Tips Section (shown before check-in)
- Take screenshot for offline access
- Arrive 15 minutes early
- Ensure screen brightness is high
- Can also show confirmation email

### Access Flow
```
Registration Complete
  ↓
Confirmation Email (includes link)
  ↓
/my-ticket/{registration_id}
  ↓
Display QR Code
  ↓
Scan at Event
  ↓
Show "Checked In" status
```

### Security
- Registration ID is the only authentication
- Keep link private (treat like a ticket)
- Can't be checked in twice
- Token is 64-character random string

### Code Location
- **Controller:** `app/Http/Controllers/RegistrationController.php::myTicket()`
- **View:** `resources/js/Pages/Registrations/MyTicket.vue`
- **QR Generation:** Base64-encoded PNG image

---

## Backend API

### Check-in Endpoint

**Route:** `POST /check-in/scan`

**Request:**
```json
{
  "qr_token": "64-character-random-string"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Welcome to Event Name",
  "attendee_name": "John Doe",
  "attendee_email": "john@example.com",
  "ticket_type": "General Admission"
}
```

**Error Responses:**

**404 - Invalid QR Code:**
```json
{
  "success": false,
  "error": "Invalid QR Code",
  "message": "This QR code was not found in our system."
}
```

**400 - Already Checked In:**
```json
{
  "success": false,
  "error": "Already Checked In",
  "message": "Already checked in 5 minutes ago",
  "attendee_name": "John Doe",
  "attendee_email": "john@example.com"
}
```

**400 - Other Validation Errors:**
- Registration Cancelled
- On Waitlist
- Invalid Status
- Payment Pending

**403 - Unauthorized:**
User doesn't have permission to check in for this event.

### Validation Logic

1. **QR Token Valid?** → 404 if not found
2. **User Authorized?** → 403 if can't manage event
3. **Already Checked In?** → 400 with check-in time
4. **Status Check:**
   - Cancelled → 400
   - Waitlisted → 400
   - Must be "confirmed" → 400 if not
5. **Payment Check:**
   - Paid tickets → Must have completed order
6. **Create Check-in Record**

### Database Schema

**check_ins table:**
```
id
registration_id (FK)
checked_in_by (staff name)
checked_in_at (timestamp)
created_at
updated_at
```

---

## User Workflows

### Event Creator Workflow
```
1. Create Event
2. Publish Event
3. View registrations on Admin Dashboard
4. Monitor check-in stats
5. Open Scanner Interface when event starts
6. (Or) Share scanner link with door staff
7. Monitor real-time check-ins
8. View final attendance numbers
```

### Door Staff Workflow
```
1. Open Scanner Interface
2. Allow camera access
3. Point camera at attendee's QR code
4. See success/error message
5. Allow attendee entry on success
6. Handle errors appropriately
7. Monitor recent scans list
```

### Participant Workflow
```
1. Register for event
2. Receive confirmation email
3. Click "View My Ticket" link
4. Save/screenshot QR code
5. Arrive at event
6. Show QR code to scanner
7. Hear beep + see success message
8. Enter event
9. (Optional) Check ticket page shows "Checked In"
```

---

## Routes Summary

| Route | Method | Auth | Purpose | Component |
|-------|--------|------|---------|-----------|
| `/events/{event}` | GET | Yes | Admin dashboard | `Events/Show.vue` |
| `/events/{event}/check-in` | GET | Yes | Scanner interface | `CheckIn/Scanner.vue` |
| `/my-ticket/{registration}` | GET | No | Participant ticket | `Registrations/MyTicket.vue` |
| `/check-in/scan` | POST | Yes | Process scan | API endpoint |
| `/registrations/{registration}/download` | GET | No | Download PDF | PDF generator |

---

## Mobile Optimization

### Scanner Interface
- Responsive design
- Large touch targets
- Clear visual feedback
- Works on tablets and phones
- Portrait and landscape support

### Participant View
- Mobile-first design
- Large QR code display
- Easy screenshot capture
- Offline-friendly (screenshot)
- Shareable link

---

## Error Handling

### Scanner Interface
- **Camera access denied:** Show alert with instructions
- **Network error:** Show error message, allow retry
- **Invalid scan:** Red overlay with clear message
- **Already checked in:** Yellow warning with time

### Participant View
- **Invalid registration:** 404 error page
- **Event not found:** Friendly error message
- **QR code generation failed:** Fallback to token display

---

## Performance Considerations

### Scanner
- Scan interval: 300ms (prevents duplicate scans)
- Video resolution: Auto-detected
- Canvas rendering: Efficient frame capture
- Memory cleanup: Proper stream disposal

### Participant View
- QR code pre-generated on server
- Base64 inline image (no additional request)
- Lightweight page (fast load on mobile data)

---

## Security

1. **Authentication:**
   - Admin/Scanner: Requires login + event ownership
   - Participant: Secret registration ID (like a ticket number)

2. **Authorization:**
   - `canManageEvent()` check for check-ins
   - Policy-based access control

3. **QR Token:**
   - 64-character random string
   - Generated on registration creation
   - Unique per registration
   - Cannot be guessed

4. **Duplicate Prevention:**
   - Check-in record prevents re-scanning
   - Database constraint on registration_id

---

## Future Enhancements

### Potential Features
- [ ] Offline mode for scanner (local storage)
- [ ] Batch check-in (multiple attendees)
- [ ] Manual check-in (search by name/email)
- [ ] Export check-in report
- [ ] Push notifications on check-in
- [ ] Apple Wallet / Google Pay integration
- [ ] Check-in analytics dashboard
- [ ] Multiple scanner stations
- [ ] Check-out tracking

---

## Testing Guide

### Test Scenarios

**Admin Dashboard:**
1. View event with 0 registrations
2. View event with registrations, 0 check-ins
3. View event with some checked in
4. View event with all checked in
5. Recent check-ins update correctly

**Scanner Interface:**
1. Camera permission granted
2. Camera permission denied
3. Valid QR code scan
4. Invalid QR code scan
5. Already checked-in scan
6. Cancelled registration scan
7. Network error during scan
8. Rapid successive scans

**Participant View:**
1. Valid registration (not checked in)
2. Valid registration (already checked in)
3. Invalid registration ID
4. Download ticket PDF
5. Share ticket link
6. Screenshot QR code

---

## Troubleshooting

### Scanner Not Working
- Check camera permissions in browser
- Try different browser (Chrome recommended)
- Ensure HTTPS (camera requires secure context)
- Check lighting conditions
- Clean camera lens

### QR Code Won't Scan
- Increase screen brightness
- Hold steady in scanner box
- Ensure code is fully visible
- Try different angle
- Screenshot and display full screen

### Already Checked In Error
- Verify it's the correct attendee
- Check recent scans list
- View check-in time in admin dashboard
- Contact event organizer if incorrect

---

## Development Notes

### Key Files Modified
- `app/Http/Controllers/EventController.php` - Enhanced show() method
- `app/Http/Controllers/CheckInController.php` - Scanner index and scan methods
- `app/Http/Controllers/RegistrationController.php` - Added myTicket() method
- `resources/js/Pages/Events/Show.vue` - Added check-in overview
- `resources/js/Pages/CheckIn/Scanner.vue` - NEW: Scanner interface
- `resources/js/Pages/Registrations/MyTicket.vue` - NEW: Participant view
- `routes/web.php` - Added new routes

### Dependencies Added
- `jsqr` - QR code reading library (already installed)

### Database Relations
```
Event
  ↓ hasMany
Registration
  ↓ hasOne
CheckIn
```

---

## Summary

This three-interface system provides:

✅ **Admin Dashboard** - Complete oversight and management  
✅ **Scanner Interface** - Fast, focused check-in experience  
✅ **Participant View** - Simple, mobile-friendly ticket display  

Each interface is optimized for its specific use case while sharing the same backend logic for consistency and reliability.
