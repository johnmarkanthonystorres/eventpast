# Strapi CMS Integration Guide

## Overview

EventPast uses Strapi v5 as a headless CMS for managing editorial content. Laravel handles all event data while Strapi manages:
- Landing pages
- Blog posts
- Help articles
- Event playbooks
- Featured event curation

## Architecture

```
┌─────────────┐         ┌──────────────┐
│   Laravel   │ ──────> │    Strapi    │
│  (Events)   │  HTTP   │  (Content)   │
│             │ <────── │              │
└─────────────┘ Webhook └──────────────┘
```

### Integration Flow

1. **Laravel → Strapi**: Laravel reads content via REST API with caching (15 min)
2. **Strapi → Laravel**: Webhooks trigger cache clearing on publish/unpublish
3. **Security**: API token for reads, webhook secret for writes

## Setup Instructions

### 1. Install Strapi

```bash
cd C:/laragon/www/eventpast-strapi
npm install
```

### 2. Configure Environment

Create `.env` in the Strapi directory:

```env
HOST=0.0.0.0
PORT=1337
APP_KEYS=toBeGenerated
API_TOKEN_SALT=toBeGenerated
ADMIN_JWT_SECRET=toBeGenerated
TRANSFER_TOKEN_SALT=toBeGenerated
JWT_SECRET=toBeGenerated

DATABASE_CLIENT=sqlite
DATABASE_FILENAME=.tmp/data.db
```

### 3. Start Strapi

```bash
npm run develop
```

This will:
- Start Strapi on http://localhost:1337
- Open admin panel at http://localhost:1337/admin
- Prompt you to create an admin account

### 4. Create Content Types

In the Strapi admin panel, create these content types:

#### Landing Page
- **Collection Type**
- Fields:
  - `title` (Text, required)
  - `slug` (UID, required)
  - `hero` (Rich Text)
  - `features` (JSON)
  - `testimonials` (JSON)
  - `cta` (Component)
  - `seo` (Component: title, description, keywords)

#### Blog Post
- **Collection Type**
- Fields:
  - `title` (Text, required)
  - `slug` (UID, required)
  - `content` (Rich Text, required)
  - `excerpt` (Text)
  - `author` (Relation: belongs to User)
  - `featuredImage` (Media)
  - `publishedAt` (DateTime)
  - `seo` (Component)

#### Help Article
- **Collection Type**
- Fields:
  - `title` (Text, required)
  - `category` (Enumeration: Getting Started, Events, Registrations, Payments, Troubleshooting)
  - `content` (Rich Text, required)
  - `order` (Number)
  - `relatedArticles` (Relation: has many Help Articles)

#### Featured Event
- **Collection Type**
- Fields:
  - `eventId` (Number, required) - References Laravel event ID
  - `displayOrder` (Number)
  - `featuredImage` (Media)
  - `description` (Text)

#### Playbook
- **Collection Type**
- Fields:
  - `title` (Text, required)
  - `slug` (UID, required)
  - `content` (Rich Text, required)
  - `difficulty` (Enumeration: Beginner, Intermediate, Advanced)
  - `estimatedTime` (Text)
  - `category` (Enumeration: Marketing, Operations, Growth)

### 5. Generate API Token

1. Go to Settings → API Tokens
2. Create new token with "Read-only" permissions
3. Copy the token
4. Add to Laravel `.env`:

```env
STRAPI_API_TOKEN=your_token_here
```

### 6. Configure Webhooks

1. Go to Settings → Webhooks
2. Create webhook for "Entry publish":
   - Name: Content Published
   - URL: http://localhost:8000/api/webhooks/strapi/content-published
   - Events: entry.publish
   - Headers: 
     ```
     X-Webhook-Secret: your_secret_here
     ```

3. Create webhook for "Entry unpublish":
   - Name: Content Unpublished
   - URL: http://localhost:8000/api/webhooks/strapi/content-unpublished
   - Events: entry.unpublish
   - Headers: Same secret as above

4. Add secret to Laravel `.env`:

```env
STRAPI_WEBHOOK_SECRET=your_secret_here
```

## Laravel Integration

### StrapiService Usage

```php
use App\Services\StrapiService;

// In a controller
public function index(StrapiService $strapiService)
{
    // Get blog posts
    $posts = $strapiService->getBlogPosts($page = 1, $perPage = 10);
    
    // Get single blog post
    $post = $strapiService->getBlogPost('how-to-fill-your-first-100-seats');
    
    // Get help articles
    $articles = $strapiService->getHelpArticles('Getting Started');
    
    // Get featured events
    $featured = $strapiService->getFeaturedEvents();
    
    // Manual cache clear
    $strapiService->clearCache();
}
```

### Available Routes

**Public Content Pages:**
- `GET /content/pages/{slug}` - Landing pages
- `GET /content/blog` - Blog index
- `GET /content/blog/{slug}` - Single blog post
- `GET /content/help?category=x` - Help center
- `GET /content/playbooks` - Event playbooks

**API Endpoints:**
- `POST /api/webhooks/strapi/content-published` - Webhook handler
- `POST /api/webhooks/strapi/content-unpublished` - Webhook handler
- `POST /api/strapi/clear-cache` - Manual cache clear (authenticated)

## Caching Strategy

- **Cache Duration**: 15 minutes (900 seconds)
- **Cache Keys**: Prefixed with `strapi.`
- **Cache Invalidation**: Automatic via webhooks when content is published/unpublished
- **Manual Clear**: Available via admin endpoint

### Cache Keys Pattern

```
strapi.landing_page.{slug}
strapi.blog_posts.page_{number}
strapi.blog_post.{slug}
strapi.help_articles.all
strapi.help_articles.{category}
strapi.featured_events
strapi.playbooks
```

## Development Workflow

### 1. Content Editing

1. Content editors work in Strapi admin (http://localhost:1337/admin)
2. Create/edit content as drafts
3. Preview changes (coming soon)
4. Publish when ready
5. Webhook automatically clears Laravel cache
6. Changes appear on Laravel frontend within seconds

### 2. Testing Content

```bash
# Check Strapi health
curl http://localhost:1337/_health

# Test blog posts endpoint
curl http://localhost:1337/api/blog-posts

# Test Laravel integration
curl http://localhost:8000/content/blog
```

### 3. Clear Cache Manually

```bash
# Via Laravel artisan
php artisan cache:forget strapi.*

# Via API (authenticated)
curl -X POST http://localhost:8000/api/strapi/clear-cache \
  -H "Authorization: Bearer {your_token}"
```

## Production Deployment

### Strapi Server

```bash
# Build for production
npm run build

# Start production server
NODE_ENV=production npm start
```

**Environment Variables:**
- Use PostgreSQL instead of SQLite
- Set proper `APP_KEYS` and secrets
- Configure S3 for media storage
- Enable CORS for Laravel domain

### Laravel Configuration

Update `.env` for production:

```env
STRAPI_URL=https://cms.eventpast.com
STRAPI_API_TOKEN=production_token
STRAPI_WEBHOOK_SECRET=production_secret
STRAPI_CACHE_DURATION=1800  # 30 minutes in production
```

### Hosting Options

- **Strapi**: Railway, Heroku, DigitalOcean App Platform, AWS ECS
- **Database**: PostgreSQL (Heroku Postgres, AWS RDS, Supabase)
- **Media Storage**: AWS S3, Cloudflare R2, DigitalOcean Spaces

## Security Considerations

1. **API Token**: Read-only, rotate periodically
2. **Webhook Secret**: Strong random string, never commit to git
3. **CORS**: Restrict to Laravel domain only
4. **Rate Limiting**: Enable on both Laravel and Strapi
5. **Admin Access**: Limit to trusted IPs, enable 2FA

## Troubleshooting

### Strapi not starting
```bash
# Check port 1337 is available
netstat -ano | findstr :1337

# Clear .tmp folder
rm -rf .tmp
```

### Cache not clearing
- Verify webhook secret matches in both systems
- Check Laravel logs: `tail -f storage/logs/laravel.log`
- Test webhook manually with curl

### Content not appearing
- Check cache: `php artisan cache:clear`
- Verify API token is valid
- Check Strapi API permissions

## Next Steps

1. ✅ Install and configure Strapi
2. ✅ Create content types
3. ✅ Generate API token
4. ✅ Set up webhooks
5. ⏳ Create Vue components for content display
6. ⏳ Add SEO meta tags
7. ⏳ Implement search functionality
8. ⏳ Build admin content management UI

## Resources

- [Strapi Documentation](https://docs.strapi.io)
- [Laravel HTTP Client](https://laravel.com/docs/http-client)
- [Inertia.js](https://inertiajs.com)
