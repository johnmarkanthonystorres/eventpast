<?php

namespace App\Http\Controllers;

use App\Services\StrapiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    protected StrapiService $strapiService;

    public function __construct(StrapiService $strapiService)
    {
        $this->strapiService = $strapiService;
    }

    /**
     * Display landing page
     */
    public function showLandingPage(string $slug): Response
    {
        $page = $this->strapiService->getLandingPage($slug);

        if (!$page) {
            abort(404);
        }

        return Inertia::render('Content/LandingPage', [
            'page' => $page
        ]);
    }

    /**
     * Display blog listing
     */
    public function blogIndex(Request $request): Response
    {
        $page = $request->input('page', 1);
        $posts = $this->strapiService->getBlogPosts($page);

        return Inertia::render('Content/BlogIndex', [
            'posts' => $posts['data'] ?? [],
            'meta' => $posts['meta'] ?? []
        ]);
    }

    /**
     * Display single blog post
     */
    public function showBlogPost(string $slug): Response
    {
        $post = $this->strapiService->getBlogPost($slug);

        if (!$post) {
            abort(404);
        }

        return Inertia::render('Content/BlogPost', [
            'post' => $post
        ]);
    }

    /**
     * Display help center
     */
    public function helpCenter(Request $request): Response
    {
        $category = $request->input('category');
        $articles = $this->strapiService->getHelpArticles($category);

        return Inertia::render('Content/HelpCenter', [
            'articles' => $articles['data'] ?? [],
            'selectedCategory' => $category
        ]);
    }

    /**
     * Display event playbooks
     */
    public function playbooks(): Response
    {
        $playbooks = $this->strapiService->getPlaybooks();

        return Inertia::render('Content/Playbooks', [
            'playbooks' => $playbooks['data'] ?? []
        ]);
    }

    /**
     * Get featured events for homepage
     */
    public function featuredEvents(): array
    {
        $featured = $this->strapiService->getFeaturedEvents();
        return $featured['data'] ?? [];
    }
}
