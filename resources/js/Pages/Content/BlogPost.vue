<template>
    <Head :title="post?.attributes?.title || 'Blog Post'" />
    
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <Link
                    :href="route('content.blog.index')"
                    class="text-indigo-600 hover:text-indigo-700 mb-4 inline-block"
                >
                    ← Back to Blog
                </Link>
            </div>
        </header>

        <!-- Article -->
        <main class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <article v-if="post" class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Featured Image -->
                <div v-if="post.attributes.featuredImage" class="aspect-video bg-gray-200">
                    <img
                        :src="getStrapiImageUrl(post.attributes.featuredImage)"
                        :alt="post.attributes.title"
                        class="w-full h-full object-cover"
                    />
                </div>

                <!-- Content -->
                <div class="p-8 lg:p-12">
                    <!-- Title -->
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">
                        {{ post.attributes.title }}
                    </h1>

                    <!-- Meta -->
                    <div class="flex items-center text-gray-600 text-sm mb-8 pb-8 border-b">
                        <span v-if="post.attributes.author">
                            By {{ post.attributes.author.data?.attributes?.username || 'EventPast Team' }}
                        </span>
                        <span v-if="post.attributes.publishedAt" class="mx-2">•</span>
                        <span v-if="post.attributes.publishedAt">
                            {{ formatDate(post.attributes.publishedAt) }}
                        </span>
                    </div>

                    <!-- Content -->
                    <div 
                        class="prose prose-lg max-w-none"
                        v-html="post.attributes.content"
                    ></div>
                </div>
            </article>

            <!-- CTA Section -->
            <div class="mt-12 bg-indigo-600 rounded-lg p-8 text-center text-white">
                <h2 class="text-2xl font-bold mb-4">Ready to create your event?</h2>
                <p class="mb-6">Join thousands of organizers using EventPast to manage their events</p>
                <Link
                    :href="route('register')"
                    class="inline-block bg-white text-indigo-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition"
                >
                    Get Started Free
                </Link>
            </div>
        </main>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    post: {
        type: Object,
        required: true
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

<style scoped>
.prose {
    @apply text-gray-700 leading-relaxed;
}

.prose :deep(h2) {
    @apply text-2xl font-bold text-gray-900 mt-8 mb-4;
}

.prose :deep(h3) {
    @apply text-xl font-semibold text-gray-900 mt-6 mb-3;
}

.prose :deep(p) {
    @apply mb-4;
}

.prose :deep(ul), .prose :deep(ol) {
    @apply mb-4 ml-6;
}

.prose :deep(li) {
    @apply mb-2;
}

.prose :deep(a) {
    @apply text-indigo-600 hover:text-indigo-700 underline;
}

.prose :deep(img) {
    @apply rounded-lg my-6;
}

.prose :deep(blockquote) {
    @apply border-l-4 border-indigo-500 pl-4 italic text-gray-700 my-6;
}

.prose :deep(code) {
    @apply bg-gray-100 px-2 py-1 rounded text-sm font-mono;
}
</style>
