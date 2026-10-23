<?php

namespace App\Http\Controllers;

use App\Services\StrapiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StrapiWebhookController extends Controller
{
    protected StrapiService $strapiService;

    public function __construct(StrapiService $strapiService)
    {
        $this->strapiService = $strapiService;
    }

    /**
     * Handle content published webhook from Strapi
     */
    public function handleContentPublished(Request $request): JsonResponse
    {
        // Verify webhook secret for security
        $secret = $request->header('X-Webhook-Secret');
        $expectedSecret = config('services.strapi.webhook_secret');

        if ($secret !== $expectedSecret) {
            Log::warning('Invalid Strapi webhook secret received');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $model = $payload['model'] ?? null;
        $entry = $payload['entry'] ?? null;

        if (!$model || !$entry) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info("Strapi content published", [
            'model' => $model,
            'entry_id' => $entry['id'] ?? null
        ]);

        // Clear cache based on content type
        $this->clearRelevantCache($model, $entry);

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }

    /**
     * Handle content unpublished webhook
     */
    public function handleContentUnpublished(Request $request): JsonResponse
    {
        $secret = $request->header('X-Webhook-Secret');
        $expectedSecret = config('services.strapi.webhook_secret');

        if ($secret !== $expectedSecret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $model = $payload['model'] ?? null;
        
        if (!$model) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        Log::info("Strapi content unpublished", ['model' => $model]);
        
        $this->clearRelevantCache($model, $payload['entry'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ]);
    }

    /**
     * Clear cache based on content type
     */
    protected function clearRelevantCache(string $model, array $entry): void
    {
        switch ($model) {
            case 'landing-page':
                $slug = $entry['slug'] ?? null;
                if ($slug) {
                    $this->strapiService->clearCache("landing_page.{$slug}");
                }
                break;

            case 'blog-post':
                // Clear all blog cache when a post is published/unpublished
                $this->strapiService->clearCache('blog_posts.*');
                $slug = $entry['slug'] ?? null;
                if ($slug) {
                    $this->strapiService->clearCache("blog_post.{$slug}");
                }
                break;

            case 'help-article':
                $category = $entry['category'] ?? null;
                $this->strapiService->clearCache('help_articles.all');
                if ($category) {
                    $this->strapiService->clearCache("help_articles.{$category}");
                }
                break;

            case 'featured-event':
                $this->strapiService->clearCache('featured_events');
                break;

            case 'playbook':
                $this->strapiService->clearCache('playbooks');
                break;

            default:
                // Clear everything if we don't recognize the model
                $this->strapiService->clearCache();
        }
    }

    /**
     * Manual cache clear endpoint (admin only)
     */
    public function clearAllCache(): JsonResponse
    {
        $this->strapiService->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'All Strapi cache cleared'
        ]);
    }
}
