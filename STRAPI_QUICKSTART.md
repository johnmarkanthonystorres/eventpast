# 🚀 Strapi CMS Quick Start Guide

## Current Status
- ✅ Laravel integration complete
- ✅ Vue components built
- ⏳ Strapi npm install in progress (background)

## What to Do Next

### Step 1: Check Strapi Installation Status

```bash
cd C:/laragon/www/eventpast-cms
# The official installer is creating the project
# Wait for it to complete (shown in terminal)
```

### Step 2: Start Strapi Development Server

```bash
npm run develop
```

**First time will take 2-3 minutes** - it's building the admin panel.

### Step 3: Create Admin Account

Browser will open automatically at http://localhost:1337/admin

Fill in:
- First Name: Your name
- Last Name: Your name
- Email: admin@eventpast.com (or your email)
- Password: Choose a strong password

### Step 4: Generate API Token

1. Click **Settings** (bottom left)
2. Click **API Tokens** under Global settings
3. Click **Create new API Token**
4. Fill in:
   - Name: `Laravel EventPast`
   - Token type: `Read-only`
   - Duration: `Unlimited`
5. **Save** and copy the token
6. Add to Laravel `.env`:
   ```env
   STRAPI_API_TOKEN=paste_your_token_here
   STRAPI_WEBHOOK_SECRET=generate_random_string_here
   ```

### Step 6: Create Content Types

For each content type below, go to **Content-Type Builder** → **Create new collection type**:

#### 1. Blog Post
- Display name: `Blog Post`
- API ID (singular): `blog-post`

**Fields:**
- `title` - Text (required)
- `slug` - UID (attached to title, required)
- `content` - Rich text (required)
- `excerpt` - Text (long)
- `publishedAt` - Date
- `featuredImage` - Media (single)

**Settings:** Enable Draft & Publish

#### 2. Help Article
- Display name: `Help Article`

**Fields:**
- `title` - Text (required)
- `category` - Enumeration (Getting Started, Events, Registrations, Payments, Troubleshooting)
- `content` - Rich text (required)
- `order` - Number (integer)

#### 3. Playbook
- Display name: `Playbook`

**Fields:**
- `title` - Text (required)
- `slug` - UID (attached to title)
- `content` - Rich text (required)
- `difficulty` - Enumeration (Beginner, Intermediate, Advanced)
- `estimatedTime` - Text
- `category` - Enumeration (Marketing, Operations, Growth)

#### 4. Landing Page
- Display name: `Landing Page`

**Fields:**
- `title` - Text (required)
- `slug` - UID (required)
- `hero` - Rich text
- `content` - Rich text

#### 5. Featured Event
- Display name: `Featured Event`

**Fields:**
- `eventId` - Number (integer, required)
- `displayOrder` - Number (integer)
- `description` - Text
- `featuredImage` - Media (single)

### Step 7: Set Permissions

1. Go to **Settings** → **Users & Permissions Plugin** → **Roles**
2. Click **Public**
3. Enable these permissions:
   - ✅ Blog-post: find, findOne
   - ✅ Help-article: find, findOne
   - ✅ Playbook: find, findOne
   - ✅ Landing-page: find, findOne
   - ✅ Featured-event: find
4. **Save**

### Step 8: Configure Webhooks

1. Go to **Settings** → **Webhooks**
2. Click **Create new webhook**

**Webhook 1: Content Published**
- Name: `Content Published`
- URL: `http://localhost:8000/api/webhooks/strapi/content-published`
- Events: Check `entry.publish`
- Headers:
  ```
  X-Webhook-Secret: your_secret_from_.env
  ```

**Webhook 2: Content Unpublished**
- Name: `Content Unpublished`
- URL: `http://localhost:8000/api/webhooks/strapi/content-unpublished`
- Events: Check `entry.unpublish`
- Headers: Same as above

### Step 9: Create Test Content

1. Go to **Content Manager**
2. Create a test blog post:
   - Title: "How to Create Your First Event"
   - Slug: auto-generated
   - Content: Write something
   - Publish it!

3. Create a help article:
   - Title: "Getting Started with EventPast"
   - Category: Getting Started
   - Content: Write instructions
   - Save & Publish

### Step 10: Test Integration

```bash
# Make sure Laravel is running
cd C:/laragon/www/eventpast
php artisan serve

# In another terminal, make sure Vite is running
npm run dev
```

**Visit:**
- http://localhost:8000/content/blog (should show your blog post)
- http://localhost:8000/content/help (should show help articles)

## Troubleshooting

### Strapi won't start
```bash
cd C:/laragon/www/eventpast-strapi
rm -rf .tmp
npm run develop
```

### Blog shows empty
- Check STRAPI_API_TOKEN is set in Laravel .env
- Check blog post is published (not draft)
- Check public permissions are enabled in Strapi
- Clear Laravel cache: `php artisan cache:clear`

### Webhooks not working
- Check webhook secret matches in both .env files
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Test manually: Create/publish content and check if it appears immediately

## Need Help?

See `STRAPI_INTEGRATION.md` for detailed documentation.

---

## Quick Reference

**Strapi Admin:** http://localhost:1337/admin  
**Laravel App:** http://localhost:8000  
**Blog:** http://localhost:8000/content/blog  
**Help Center:** http://localhost:8000/content/help  

**Commands:**
```bash
# Strapi
npm run develop    # Development mode
npm run build      # Build admin panel
npm start          # Production mode

# Laravel
php artisan serve  # Start server
php artisan cache:clear  # Clear cache

# Clear Strapi cache from Laravel
curl -X POST http://localhost:8000/api/strapi/clear-cache \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

**Estimated Setup Time:** 20-30 minutes  
**Ready to create content!** 🎉
