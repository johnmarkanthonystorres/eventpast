<template>
    <AppLayout>
        <div class="max-w-6xl mx-auto">
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <div class="flex items-center space-x-3 mb-2">
                        <h1 class="text-3xl font-bold text-gray-900">{{ event.title }}</h1>
                        <span
                            :class="{
                                'bg-green-100 text-green-800': event.status === 'published',
                                'bg-yellow-100 text-yellow-800': event.status === 'draft',
                                'bg-gray-100 text-gray-800': event.status === 'ended',
                                'bg-red-100 text-red-800': event.status === 'cancelled',
                            }"
                            class="px-3 py-1 text-sm font-semibold rounded-full"
                        >
                            {{ event.status }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600">{{ formatDate(event.start_at) }}</p>
                </div>
                <div class="flex space-x-2">
                    <Link
                        :href="route('events.edit', event.id)"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Edit Event
                    </Link>
                    <Link
                        :href="route('events.referrals', event.id)"
                        class="px-4 py-2 bg-purple-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-purple-700"
                    >
                        View Referrals
                    </Link>
                    <button
                        v-if="event.status === 'draft'"
                        @click="publishEvent"
                        class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700"
                    >
                        Publish Event
                    </button>
                    <Link
                        v-if="event.status === 'published'"
                        :href="route('events.checkin', event.id)"
                        class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Check-in Scanner
                    </Link>
                </div>
            </div>

            <!-- Event Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Details Card -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Event Details</h2>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ event.description || 'No description provided' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date & Time</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ formatDate(event.start_at) }} - {{ formatDate(event.end_at) }}
                                    <span class="text-gray-500">({{ event.timezone }})</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Location</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span v-if="event.venue_name">{{ event.venue_name }}</span>
                                    <span v-else-if="event.online_link">Online Event</span>
                                    <span v-else>No location specified</span>
                                    <p v-if="event.venue_address" class="text-gray-600 mt-1">{{ event.venue_address }}</p>
                                    <a v-if="event.online_link" :href="event.online_link" target="_blank" class="text-blue-600 hover:text-blue-800 mt-1 inline-block">
                                        {{ event.online_link }}
                                    </a>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Capacity</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ event.capacity || 'Unlimited' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Check-in Overview (Admin) -->
                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Check-in Overview</h2>
                            <Link
                                v-if="event.status === 'published'"
                                :href="route('events.checkin', event.id)"
                                class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
                            >
                                Open Scanner
                            </Link>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-blue-50 rounded-lg p-4 text-center">
                                <p class="text-3xl font-bold text-blue-600">{{ checkedInCount }}</p>
                                <p class="text-xs text-gray-600 mt-1">Checked In</p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4 text-center">
                                <p class="text-3xl font-bold text-green-600">{{ confirmedCount - checkedInCount }}</p>
                                <p class="text-xs text-gray-600 mt-1">Pending</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <p class="text-3xl font-bold text-gray-600">{{ checkInRate }}%</p>
                                <p class="text-xs text-gray-600 mt-1">Check-in Rate</p>
                            </div>
                        </div>

                        <!-- Recent Check-ins -->
                        <div v-if="recentCheckIns.length > 0" class="border-t pt-4">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Recent Check-ins</h3>
                            <div class="space-y-2">
                                <div v-for="checkIn in recentCheckIns.slice(0, 5)" :key="checkIn.id" 
                                     class="flex justify-between items-center text-sm">
                                    <span class="text-gray-900">{{ checkIn.registration.attendee.name }}</span>
                                    <span class="text-gray-500">{{ formatTimeAgo(checkIn.checked_in_at) }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500 text-center py-4 border-t mt-4">No check-ins yet</p>
                    </div>

                    <!-- Registrations -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Registrations</h2>
                        
                        <div v-if="event.registrations.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="registration in event.registrations" :key="registration.id">
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ registration.attendee.name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ registration.attendee.email }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ registration.ticket_type?.name || 'N/A' }}</td>
                                        <td class="px-4 py-3">
                                            <span v-if="registration.check_in" class="text-green-600 text-sm flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Checked In
                                            </span>
                                            <span v-else class="text-gray-400 text-sm">Not yet</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                :class="{
                                                    'bg-green-100 text-green-800': registration.status === 'confirmed',
                                                    'bg-blue-100 text-blue-800': registration.status === 'checked_in',
                                                    'bg-yellow-100 text-yellow-800': registration.status === 'waitlisted',
                                                    'bg-red-100 text-red-800': registration.status === 'cancelled',
                                                }"
                                                class="px-2 py-1 text-xs font-medium rounded-full"
                                            >
                                                {{ registration.status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ formatShortDate(registration.created_at) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-sm text-gray-500 text-center py-8">No registrations yet</p>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Stats -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">Statistics</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs text-gray-500">Total Registrations</dt>
                                <dd class="text-2xl font-bold text-gray-900">{{ event.registrations.length }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Confirmed</dt>
                                <dd class="text-lg font-semibold text-green-600">{{ confirmedCount }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Waitlisted</dt>
                                <dd class="text-lg font-semibold text-yellow-600">{{ waitlistedCount }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Checked In</dt>
                                <dd class="text-lg font-semibold text-blue-600">{{ checkedInCount }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Public Link -->
                    <div v-if="event.status === 'published'" class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-2">Public Registration Link</h3>
                        <div class="flex items-center space-x-2">
                            <input 
                                :value="publicUrl" 
                                readonly
                                class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-md bg-white"
                            />
                            <button 
                                @click="copyPublicLink"
                                class="px-3 py-2 bg-blue-600 text-white text-xs font-medium rounded-md hover:bg-blue-700"
                            >
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>

                    <!-- Ticket Types -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">Ticket Types</h3>
                        <div v-if="event.ticket_types.length > 0" class="space-y-3">
                            <div v-for="ticket in event.ticket_types" :key="ticket.id" class="border-l-4 border-blue-500 pl-3">
                                <p class="text-sm font-medium text-gray-900">{{ ticket.name }}</p>
                                <p class="text-xs text-gray-600">{{ ticket.price > 0 ? `$${ticket.price}` : 'Free' }}</p>
                                <p class="text-xs text-gray-500">{{ ticket.quantity_sold || 0 }} sold</p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">No ticket types created</p>
                        <button class="mt-4 w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Add Ticket Type
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
});

const copied = ref(false);

const confirmedCount = computed(() => {
    return props.event.registrations.filter(r => r.status === 'confirmed').length;
});

const waitlistedCount = computed(() => {
    return props.event.registrations.filter(r => r.status === 'waitlisted').length;
});

const checkedInCount = computed(() => {
    return props.event.registrations.filter(r => r.check_in !== null).length;
});

const checkInRate = computed(() => {
    if (confirmedCount.value === 0) return 0;
    return Math.round((checkedInCount.value / confirmedCount.value) * 100);
});

const recentCheckIns = computed(() => {
    return props.event.registrations
        .filter(r => r.check_in !== null)
        .sort((a, b) => new Date(b.check_in.checked_in_at) - new Date(a.check_in.checked_in_at));
});

const publicUrl = computed(() => {
    return `${window.location.origin}/events/${props.event.slug}`;
});

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatShortDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const formatTimeAgo = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
};

const copyPublicLink = () => {
    navigator.clipboard.writeText(publicUrl.value);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const publishEvent = () => {
    if (confirm('Are you sure you want to publish this event? It will be visible to the public.')) {
        router.put(route('events.update', props.event.id), {
            ...props.event,
            status: 'published',
        });
    }
};
</script>
