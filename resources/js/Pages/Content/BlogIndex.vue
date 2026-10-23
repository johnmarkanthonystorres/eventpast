<template>
    <Head title="Blog" />
    
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">Blog</h1>
                <p class="mt-2 text-gray-600">Tips, guides, and insights for event organizers</p>
            </div>
        </header>

        <!-- Blog Posts Grid -->
        <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div v-if="posts.length === 0" class="text-center py-12">
                <p class="text-gray-500 text-lg">No blog posts available yet.</p>
            </div>

            <div v-else class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                <article
                    v-for="post in posts"
                    :key="post.id"
                    class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow"
                >
                    <!-- Featured Image -->
                    <div v-if="post.attributes.featuredImage" class="aspect-video bg-gray-200">
                        <img
                            :src="getStrapiImageUrl(post.attributes.featuredImage)"
                            :alt="post.attributes.title"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <div v-else class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600"></div>

                    <!-- Content -->
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">
                            <Link
                                :href="route('content.blog.show', post.attributes.slug)"
                                class="hover:text-indigo-600"
                            >
                                {{ post.attributes.title }}
                            </Link>
                        </h2>

                        <p v-if="post.attributes.excerpt" class="text-gray-600 mb-4 line-clamp-3">
                            {{ post.attributes.excerpt }}
                        </p>

                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <span v-if="post.attributes.publishedAt">
                                {{ formatDate(post.attributes.publishedAt) }}
                            </span>
                            <Link
                                :href="route('content.blog.show', post.attributes.slug)"
                                class="text-indigo-600 hover:text-indigo-700 font-medium"
                            >
                                Read more →
                            </Link>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Pagination -->
            <div v-if="meta.pagination" class="mt-12 flex justify-center">
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="meta.pagination.page > 1"
                        :href="route('content.blog.index', { page: meta.pagination.page - 1 })"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Previous
                    </Link>

                    <span class="px-4 py-2 text-gray-700">
                        Page {{ meta.pagination.page }} of {{ meta.pagination.pageCount }}
                    </span>

                    <Link
                        v-if="meta.pagination.page < meta.pagination.pageCount"
                        :href="route('content.blog.index', { page: meta.pagination.page + 1 })"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                    >
                        Next
                    </Link>
                </nav>
            </div>
        </main>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    posts: {
        type: Array,
        default: () => []
    },
    meta: {
        type: Object,
        default: () => ({})
    }
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const getStrapiImageUrl = (image) => {
    const strapiUrl = import.meta.env.VITE_STRAPI_URL || 'http://localhost:1337';
    
    if (image.data && image.data.attributes) {
        const url = image.data.attributes.url;
        return url.startsWith('http') ? url : `${strapiUrl}${url}`;
    }
    
    return '';
};
</script>
