<template>
    <Head title="Help Center" />
    
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">Help Center</h1>
                <p class="mt-2 text-gray-600">Find answers and get support</p>
            </div>
        </header>

        <!-- Content -->
        <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <!-- Category Sidebar -->
                <aside class="md:col-span-1">
                    <nav class="space-y-2">
                        <button
                            @click="selectCategory(null)"
                            :class="[
                                'w-full text-left px-4 py-2 rounded-lg transition',
                                selectedCategory === null
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            All Articles
                        </button>
                        <button
                            v-for="category in categories"
                            :key="category"
                            @click="selectCategory(category)"
                            :class="[
                                'w-full text-left px-4 py-2 rounded-lg transition',
                                selectedCategory === category
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            {{ category }}
                        </button>
                    </nav>
                </aside>

                <!-- Articles List -->
                <div class="md:col-span-3">
                    <div v-if="articles.length === 0" class="bg-white rounded-lg p-12 text-center">
                        <p class="text-gray-500">No articles found in this category.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="article in articles"
                            :key="article.id"
                            class="bg-white rounded-lg p-6 shadow-sm hover:shadow-md transition"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <span class="inline-block text-xs font-semibold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full mb-2">
                                        {{ article.attributes.category }}
                                    </span>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                        {{ article.attributes.title }}
                                    </h3>
                                    <div 
                                        class="text-gray-600 text-sm line-clamp-2"
                                        v-html="getExcerpt(article.attributes.content)"
                                    ></div>
                                </div>
                                <button
                                    @click="toggleArticle(article.id)"
                                    class="ml-4 text-indigo-600 hover:text-indigo-700"
                                >
                                    <svg
                                        :class="['w-5 h-5 transition-transform', expandedArticles.includes(article.id) ? 'rotate-180' : '']"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Expanded Content -->
                            <div
                                v-if="expandedArticles.includes(article.id)"
                                class="mt-4 pt-4 border-t prose prose-sm max-w-none"
                                v-html="article.attributes.content"
                            ></div>
                        </article>
                    </div>
                </div>
            </div>

            <!-- Contact Support CTA -->
            <div class="mt-12 bg-indigo-50 rounded-lg p-8 text-center">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Still need help?</h2>
                <p class="text-gray-600 mb-6">Our support team is here to assist you</p>
                <a
                    href="mailto:support@eventpast.com"
                    class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition"
                >
                    Contact Support
                </a>
            </div>
        </main>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps({
    articles: {
        type: Array,
        default: () => []
    },
    selectedCategory: {
        type: String,
        default: null
    }
});

const categories = [
    'Getting Started',
    'Events',
    'Registrations',
    'Payments',
    'Troubleshooting'
];

const expandedArticles = ref([]);

const selectCategory = (category) => {
    router.get(route('content.help', { category }), {}, {
        preserveState: true,
        preserveScroll: true
    });
};

const toggleArticle = (articleId) => {
    const index = expandedArticles.value.indexOf(articleId);
    if (index > -1) {
        expandedArticles.value.splice(index, 1);
    } else {
        expandedArticles.value.push(articleId);
    }
};

const getExcerpt = (content) => {
    // Strip HTML tags and get first 150 characters
    const text = content.replace(/<[^>]*>/g, '');
    return text.substring(0, 150) + (text.length > 150 ? '...' : '');
};
</script>

<style scoped>
.prose :deep(h2) {
    @apply text-xl font-bold text-gray-900 mt-6 mb-3;
}

.prose :deep(h3) {
    @apply text-lg font-semibold text-gray-900 mt-4 mb-2;
}

.prose :deep(p) {
    @apply mb-3;
}

.prose :deep(ul), .prose :deep(ol) {
    @apply mb-3 ml-4;
}

.prose :deep(li) {
    @apply mb-1;
}

.prose :deep(a) {
    @apply text-indigo-600 hover:text-indigo-700 underline;
}

.prose :deep(code) {
    @apply bg-gray-100 px-2 py-1 rounded text-sm font-mono;
}
</style>
