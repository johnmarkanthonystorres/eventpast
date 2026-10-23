<template>
    <div class="min-h-screen bg-white">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">{{ tenant?.name || 'EventPast' }}</h1>
                    <a href="#" class="text-sm text-gray-600 hover:text-gray-900">Powered by EventPast</a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-gray-50 border-t border-gray-200 mt-12">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <p class="text-center text-sm text-gray-500">
                    © {{ new Date().getFullYear() }} {{ tenant?.name || 'EventPast' }}. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const tenant = computed(() => page.props.tenant);
</script>
