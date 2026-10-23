# Quick Fix: Registration Link Not Found

## 🎯 The Issue

The public registration link uses **slugs** (like `my-event-2024`), not IDs (like `1`).

**Wrong:** ❌ `http://localhost:8000/events/1`  
**Correct:** ✅ `http://localhost:8000/events/my-event-title`

---

## ✅ Solution 1: Get the Correct Link from Admin

1. **Login** to your admin dashboard:
   ```
   http://localhost:8000/login
   Email: admin@demo.com
   Password: password
   ```

2. **Click on any event** from your dashboard

3. **Find the blue box** that says "Public Registration Link"

4. **Click "Copy"** button - this gives you the correct URL!

5. **Open in incognito/new browser** and test registration

---

## ✅ Solution 2: Build the URL Manually

If your event title is **"Tech Conference 2024"**, the slug will be:
```
tech-conference-2024
```

So the registration URL is:
```
http://localhost:8000/events/tech-conference-2024
```

**Slug Rules:**
- All lowercase
- Spaces become dashes (-)
- Special characters removed
- "My Event!" → `my-event`
- "Summer Festival 2024" → `summer-festival-2024`

---

## ✅ Solution 3: Fix Missing Slugs (If Still Not Working)

If you created events before and they don't have slugs, run this command:

```bash
# In your Laragon terminal or Command Prompt
cd C:\laragon\www\eventpast

# Run the fix script
C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php fix_event_slugs.php
```

This will automatically generate slugs for all events that don't have them.

---

## 🧪 Quick Test

### Test if Registration Works:

1. **Copy this example URL format:**
   ```
   http://localhost:8000/events/YOUR-EVENT-SLUG
   ```

2. **Replace `YOUR-EVENT-SLUG`** with your actual event slug

3. **Open in incognito mode** (Ctrl+Shift+N in Chrome)

4. **You should see:**
   - Event title and details
   - Registration form
   - Ticket types

5. **Fill out the form** and test registration

---

## 📋 Complete Step-by-Step Test

### From Admin Dashboard:

```
1. Login → http://localhost:8000/login
2. Dashboard → See your events
3. Click event → Opens event detail page
4. Scroll down → Find "Public Registration Link" blue box
5. Click "Copy" button
6. Open incognito → Paste URL
7. ✅ Registration form should appear!
```

---

## 🔍 Still Not Working?

### Check if Event is Published:

The registration page only works if the event status is **"published"**.

**To publish an event:**
1. Go to event detail page
2. If you see a green "Publish Event" button, click it
3. Now the registration link will work!

### Check the Route:

The route expects this format:
```
/events/{slug}
```

Not:
```
/events/{id}  ← This won't work for public registration
```

---

## 💡 Pro Tip: Where to Find the Link

**Always get the public link from the admin dashboard!**

The blue "Public Registration Link" box on your event page automatically generates the correct URL with the proper slug.

---

## 🎯 Example URLs

If you have these event titles:

| Event Title | Registration URL |
|-------------|------------------|
| "Tech Summit 2024" | `/events/tech-summit-2024` |
| "Music Festival" | `/events/music-festival` |
| "WordPress Meetup" | `/events/wordpress-meetup` |
| "Annual Conference" | `/events/annual-conference` |

---

## 🚀 Quick Start

**The Fastest Way:**

1. Login to admin
2. Click any event
3. Find the blue box with "Public Registration Link"
4. Click "Copy"
5. Open incognito
6. Paste and press Enter
7. ✅ Done!

---

Need more help? The registration link is always shown on the event detail page when the event is published!
