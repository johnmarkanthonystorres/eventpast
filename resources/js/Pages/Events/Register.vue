<template>
    <PublicLayout>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Referral Banner -->
            <div v-if="referralCode" class="mb-6 bg-gradient-to-r from-purple-500 to-indigo-600 rounded-lg p-4 text-white shadow-lg">
                <div class="flex items-center space-x-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <div>
                        <p class="font-semibold">You've been invited!</p>
                        <p class="text-sm text-purple-100">A friend recommended this event to you</p>
                    </div>
                </div>
            </div>
            
            <!-- Event Header -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div v-if="event.image" class="w-full h-64 bg-gray-200">
                    <img :src="event.image" :alt="event.title" class="w-full h-full object-cover" />
                </div>
                
                <div class="p-6">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ event.title }}</h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex items-start space-x-3">
                            <svg class="w-6 h-6 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Date & Time</p>
                                <p class="text-lg text-gray-900">{{ formatDate(event.start_at) }}</p>
                                <p class="text-sm text-gray-600">{{ event.timezone }}</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <svg class="w-6 h-6 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Location</p>
                                <p class="text-lg text-gray-900">{{ event.venue_name || 'Online Event' }}</p>
                                <p v-if="event.venue_address" class="text-sm text-gray-600">{{ event.venue_address }}</p>
                                <a v-if="event.online_link" :href="event.online_link" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">Join online</a>
                            </div>
                        </div>
                    </div>
                    
                    <div v-if="event.description" class="prose max-w-none mb-6">
                        <p class="text-gray-700 whitespace-pre-line">{{ event.description }}</p>
                    </div>
                    
                    <!-- Capacity Info -->
                    <div v-if="event.capacity" class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Available Seats</span>
                            <span class="text-lg font-semibold text-gray-900">{{ event.available_seats }} / {{ event.capacity }}</span>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                            <div 
                                class="bg-blue-600 h-2 rounded-full" 
                                :style="{ width: `${((event.capacity - event.available_seats) / event.capacity) * 100}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form -->
            <div class="mt-8 bg-white shadow rounded-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Register for this event</h2>
                
                <form @submit.prevent="submit">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input 
                                v-model="form.name" 
                                type="text" 
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input 
                                v-model="form.email" 
                                type="email" 
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input 
                                v-model="form.phone" 
                                type="tel"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ticket Type *</label>
                            <select 
                                v-model="form.ticket_type_id" 
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select a ticket type</option>
                                <option 
                                    v-for="ticket in event.ticket_types" 
                                    :key="ticket.id" 
                                    :value="ticket.id"
                                    :disabled="!ticket.is_available"
                                >
                                    {{ ticket.name }} - {{ formatMoney(ticket.price) }}
                                    {{ ticket.available_quantity !== null ? `(${ticket.available_quantity} available)` : '' }}
                                </option>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <input 
                                v-model="form.marketing_consent" 
                                type="checkbox"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            />
                            <label class="ml-2 block text-sm text-gray-700">
                                Send me updates about future events
                            </label>
                        </div>
                        
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Registering...' : 'Complete Registration' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatMoney } from '@/lib/money';

const props = defineProps({
    event: Object,
    tenant: Object,
    referralCode: String,
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    ticket_type_id: '',
    marketing_consent: false,
    referral_code: new URLSearchParams(window.location.search).get('ref') || '',
});

const submit = () => {
    form.post(route('public.events.register', [props.tenant.subdomain, props.event.slug]));
};

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
</script>
