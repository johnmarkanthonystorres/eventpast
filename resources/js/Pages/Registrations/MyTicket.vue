<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-8 px-4">
        <div class="max-w-md mx-auto">
            <!-- Ticket Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
                    <div class="flex items-center justify-between mb-2">
                        <h1 class="text-2xl font-bold">Your Ticket</h1>
                        <span
                            v-if="registration.checkIn"
                            class="bg-green-400 text-green-900 px-3 py-1 rounded-full text-xs font-bold"
                        >
                            ✓ CHECKED IN
                        </span>
                        <span
                            v-else
                            class="bg-white text-blue-600 px-3 py-1 rounded-full text-xs font-bold"
                        >
                            VALID
                        </span>
                    </div>
                    <p class="text-blue-100 text-sm">{{ event.title }}</p>
                </div>

                <!-- QR Code Section -->
                <div class="p-8 text-center">
                    <div v-if="!registration.checkIn" class="mb-4">
                        <p class="text-sm text-gray-600 mb-4">Show this QR code at the entrance</p>
                        <div class="inline-block p-4 bg-white border-4 border-gray-200 rounded-lg">
                            <img
                                :src="`data:image/png;base64,${qrCode}`"
                                alt="QR Code"
                                class="w-64 h-64"
                            />
                        </div>
                        <p class="text-xs text-gray-500 mt-4 font-mono">{{ registration.qr_token.substring(0, 16) }}...</p>
                    </div>

                    <!-- Already Checked In -->
                    <div v-else class="py-8">
                        <div class="w-24 h-24 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Already Checked In</h3>
                        <p class="text-sm text-gray-600">{{ formatDateTime(registration.checkIn.checked_in_at) }}</p>
                        <p v-if="registration.checkIn.checked_in_by" class="text-xs text-gray-500 mt-1">
                            By: {{ registration.checkIn.checked_in_by }}
                        </p>
                    </div>
                </div>

                <!-- Event Details -->
                <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Attendee</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ registration.attendee.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Email</dt>
                            <dd class="text-sm text-gray-600">{{ registration.attendee.email }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Ticket Type</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ registration.ticket_type?.name || 'General' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Event Date</dt>
                            <dd class="text-sm text-gray-900">{{ formatDate(event.start_at) }}</dd>
                        </div>
                        <div v-if="event.venue_name" class="flex justify-between">
                            <dt class="text-sm text-gray-500">Location</dt>
                            <dd class="text-sm text-gray-900">{{ event.venue_name }}</dd>
                        </div>
                        <div v-if="event.venue_address">
                            <p class="text-xs text-gray-600 mt-1">{{ event.venue_address }}</p>
                        </div>
                        <div v-if="event.online_link" class="flex justify-between">
                            <dt class="text-sm text-gray-500">Online Link</dt>
                            <dd class="text-sm">
                                <a :href="event.online_link" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                                    Join Event
                                </a>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Actions -->
                <div class="border-t border-gray-200 px-6 py-4 space-y-2">
                    <button
                        @click="downloadTicket"
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                    >
                        📥 Download Ticket (PDF)
                    </button>
                    <button
                        @click="shareTicket"
                        class="w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium"
                    >
                        📤 Share Ticket
                    </button>
                </div>

                <!-- Footer -->
                <div class="bg-gray-100 px-6 py-3 text-center">
                    <p class="text-xs text-gray-500">
                        Registration ID: #{{ registration.id }}
                    </p>
                </div>
            </div>

            <!-- Tips -->
            <div v-if="!registration.checkIn" class="mt-6 bg-white rounded-lg shadow p-4">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">💡 Quick Tips</h4>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li>• Take a screenshot of this QR code for offline access</li>
                    <li>• Arrive 15 minutes early for smooth check-in</li>
                    <li>• Make sure your screen brightness is high when scanning</li>
                    <li>• You can also show the confirmation email</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    registration: Object,
    event: Object,
    qrCode: String, // Base64 encoded QR code image
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

const formatDateTime = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const downloadTicket = () => {
    // Trigger PDF download
    window.location.href = route('registrations.download', props.registration.id);
};

const shareTicket = async () => {
    const shareData = {
        title: `Ticket: ${props.event.title}`,
        text: `My ticket for ${props.event.title}`,
        url: window.location.href,
    };

    if (navigator.share) {
        try {
            await navigator.share(shareData);
        } catch (err) {
            copyToClipboard();
        }
    } else {
        copyToClipboard();
    }
};

const copyToClipboard = () => {
    navigator.clipboard.writeText(window.location.href);
    alert('Ticket link copied to clipboard!');
};
</script>
