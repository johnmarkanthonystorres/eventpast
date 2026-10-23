<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StrapiService
{
    protected string $baseUrl;
    protected string $apiToken;
    protected int $cacheDuration;

    public function __construct()
    {
        $this->baseUrl = config('services.strapi.url', 'http://localhost:1337');
        $this->apiToken = config('services.strapi.token', '');
        $this->cacheDuration = config('services.strapi.cache_duration', 900); // 15 minutes default
    }

    /**
     * Get landing page content by slug
     */
    public function getLandingPage(string $slug): ?array
    {
        $cacheKey = "strapi.landing_page.{$slug}";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($slug) {
            try {
                $response = $this->makeRequest("api/landing-pages", [
                    'filters' => ['slug' => ['$eq' => $slug]],
                    'populate' => '*'
                ]);

                return $response['data'][0] ?? null;
            } catch (\Exception $e) {
                Log::error("Failed to fetch landing page: {$slug}", ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get blog posts with pagination
     */
    public function getBlogPosts(int $page = 1, int $perPage = 10): array
    {
        $cacheKey = "strapi.blog_posts.page_{$page}";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($page, $perPage) {
            try {
                return $this->makeRequest("api/blog-posts", [
                    'sort' => ['publishedAt:desc'],
                    'pagination' => [
                        'page' => $page,
                        'pageSize' => $perPage
                    ],
                    'populate' => ['author', 'featuredImage']
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to fetch blog posts", ['error' => $e->getMessage()]);
                return ['data' => [], 'meta' => []];
            }
        });
    }

    /**
     * Get single blog post by slug
     */
    public function getBlogPost(string $slug): ?array
    {
        $cacheKey = "strapi.blog_post.{$slug}";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($slug) {
            try {
                $response = $this->makeRequest("api/blog-posts", [
                    'filters' => ['slug' => ['$eq' => $slug]],
                    'populate' => ['author', 'featuredImage', 'seo']
                ]);

                return $response['data'][0] ?? null;
            } catch (\Exception $e) {
                Log::error("Failed to fetch blog post: {$slug}", ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Get help articles by category
     */
    public function getHelpArticles(?string $category = null): array
    {
        $cacheKey = $category 
            ? "strapi.help_articles.{$category}" 
            : "strapi.help_articles.all";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($category) {
            try {
                $params = ['sort' => ['order:asc']];
                
                if ($category) {
                    $params['filters'] = ['category' => ['$eq' => $category]];
                }

                return $this->makeRequest("api/help-articles", $params);
            } catch (\Exception $e) {
                Log::error("Failed to fetch help articles", ['error' => $e->getMessage()]);
                return ['data' => []];
            }
        });
    }

    /**
     * Get featured events (curated list)
     */
    public function getFeaturedEvents(): array
    {
        $cacheKey = "strapi.featured_events";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () {
            try {
                return $this->makeRequest("api/featured-events", [
                    'sort' => ['displayOrder:asc'],
                    'populate' => ['featuredImage']
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to fetch featured events", ['error' => $e->getMessage()]);
                return ['data' => []];
            }
        });
    }

    /**
     * Get event playbooks
     */
    public function getPlaybooks(): array
    {
        $cacheKey = "strapi.playbooks";
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () {
            try {
                return $this->makeRequest("api/playbooks", [
                    'sort' => ['createdAt:desc'],
                    'populate' => '*'
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to fetch playbooks", ['error' => $e->getMessage()]);
                return ['data' => []];
            }
        });
    }

    /**
     * Clear all Strapi cache
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget("strapi.{$key}");
        } else {
            // Clear all Strapi-related cache
            $tags = ['landing_page', 'blog_posts', 'blog_post', 'help_articles', 'featured_events', 'playbooks'];
            foreach ($tags as $tag) {
                Cache::forget("strapi.{$tag}.*");
            }
        }
    }

    /**
     * Make HTTP request to Strapi API
     */
    protected function makeRequest(string $endpoint, array $params = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
            'Accept' => 'application/json'
        ])
        ->timeout(10)
        ->get("{$this->baseUrl}/{$endpoint}", $params);

        if ($response->failed()) {
            throw new \Exception("Strapi API request failed: {$response->status()}");
        }

        return $response->json();
    }

    /**
     * Health check - test if Strapi is reachable
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/_health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
