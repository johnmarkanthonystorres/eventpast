<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    events: Object,
    stats: Object,
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
                <Link
                    :href="route('events.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                >
                    Create Event
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-sm">Total Events</div>
                        <div class="text-3xl font-bold text-gray-900">{{ stats?.total || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-sm">Published</div>
                        <div class="text-3xl font-bold text-green-600">{{ stats?.published || 0 }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 text-sm">Total Registrations</div>
                        <div class="text-3xl font-bold text-blue-600">{{ stats?.registrations || 0 }}</div>
                    </div>
                </div>

                <!-- Events List -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Events</h3>
                        
                        <div v-if="events.data.length === 0" class="text-center py-8">
                            <p class="text-gray-500 mb-4">No events yet. Create your first event!</p>
                            <Link
                                :href="route('events.create')"
                                class="inline-block px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            >
                                Create Event
                            </Link>
                        </div>

                        <div v-else class="space-y-4">
                            <div 
                                v-for="event in events.data" 
                                :key="event.id"
                                class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition"
                            >
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <Link 
                                                :href="route('events.show', event.id)"
                                                class="text-lg font-semibold text-gray-900 hover:text-blue-600"
                                            >
                                                {{ event.title }}
                                            </Link>
                                            <span
                                                :class="{
                                                    'bg-green-100 text-green-800': event.status === 'published',
                                                    'bg-yellow-100 text-yellow-800': event.status === 'draft',
                                                    'bg-gray-100 text-gray-800': event.status === 'ended',
                                                    'bg-red-100 text-red-800': event.status === 'cancelled',
                                                }"
                                                class="px-2 py-1 text-xs font-semibold rounded-full"
                                            >
                                                {{ event.status }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">{{ formatDate(event.start_at) }}</p>
                                        <p class="text-sm text-gray-500">{{ event.registrations_count }} registrations</p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <Link
                                            :href="route('events.edit', event.id)"
                                            class="px-3 py-1 bg-white border border-gray-300 rounded text-sm hover:bg-gray-50"
                                        >
                                            Edit
                                        </Link>
                                        <Link
                                            :href="route('events.show', event.id)"
                                            class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
                                        >
                                            View
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="events.links && events.links.length > 3" class="mt-6 flex justify-center space-x-2">
                            <Link
                                v-for="link in events.links"
                                :key="link.label"
                                :href="link.url"
                                :class="{
                                    'bg-blue-600 text-white': link.active,
                                    'bg-white text-gray-700': !link.active,
                                    'opacity-50 cursor-not-allowed': !link.url,
                                }"
                                class="px-3 py-2 border border-gray-300 rounded text-sm hover:bg-gray-50"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
