<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Create New Event</h1>
                <p class="mt-1 text-sm text-gray-600">Fill in the details to create your event</p>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <form @submit.prevent="submit">
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event Title *</label>
                            <input 
                                v-model="form.title" 
                                type="text" 
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Summer Tech Conference 2026"
                            />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea 
                                v-model="form.description" 
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Tell people about your event..."
                            ></textarea>
                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date & Time *</label>
                                <input 
                                    v-model="form.start_at" 
                                    type="datetime-local" 
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                />
                                <p v-if="form.errors.start_at" class="mt-1 text-sm text-red-600">{{ form.errors.start_at }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Date & Time *</label>
                                <input 
                                    v-model="form.end_at" 
                                    type="datetime-local" 
                                    required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                />
                                <p v-if="form.errors.end_at" class="mt-1 text-sm text-red-600">{{ form.errors.end_at }}</p>
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone *</label>
                            <select 
                                v-model="form.timezone"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">Eastern Time (US)</option>
                                <option value="America/Chicago">Central Time (US)</option>
                                <option value="America/Denver">Mountain Time (US)</option>
                                <option value="America/Los_Angeles">Pacific Time (US)</option>
                                <option value="Europe/London">London</option>
                                <option value="Europe/Paris">Paris</option>
                                <option value="Asia/Tokyo">Tokyo</option>
                            </select>
                            <p v-if="form.errors.timezone" class="mt-1 text-sm text-red-600">{{ form.errors.timezone }}</p>
                        </div>

                        <!-- Location Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location Type</label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input 
                                        v-model="locationType" 
                                        type="radio" 
                                        value="venue"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Physical Venue</span>
                                </label>
                                <label class="flex items-center">
                                    <input 
                                        v-model="locationType" 
                                        type="radio" 
                                        value="online"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Online Event</span>
                                </label>
                            </div>
                        </div>

                        <!-- Venue Details -->
                        <div v-if="locationType === 'venue'" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Venue Name</label>
                                <input 
                                    v-model="form.venue_name" 
                                    type="text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Convention Center"
                                />
                                <p v-if="form.errors.venue_name" class="mt-1 text-sm text-red-600">{{ form.errors.venue_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Venue Address</label>
                                <textarea 
                                    v-model="form.venue_address" 
                                    rows="2"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="123 Main St, City, State 12345"
                                ></textarea>
                                <p v-if="form.errors.venue_address" class="mt-1 text-sm text-red-600">{{ form.errors.venue_address }}</p>
                            </div>
                        </div>

                        <!-- Online Link -->
                        <div v-if="locationType === 'online'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Online Meeting Link</label>
                            <input 
                                v-model="form.online_link" 
                                type="url"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                placeholder="https://zoom.us/j/123456789"
                            />
                            <p v-if="form.errors.online_link" class="mt-1 text-sm text-red-600">{{ form.errors.online_link }}</p>
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Event Capacity</label>
                            <input 
                                v-model.number="form.capacity" 
                                type="number"
                                min="1"
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                placeholder="100"
                            />
                            <p class="mt-1 text-xs text-gray-500">Leave empty for unlimited capacity (within plan limits)</p>
                            <p v-if="form.errors.capacity" class="mt-1 text-sm text-red-600">{{ form.errors.capacity }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                            <Link
                                :href="route('events.index')"
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button 
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Creating...' : 'Create Event' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const locationType = ref('venue');

const form = useForm({
    title: '',
    description: '',
    start_at: '',
    end_at: '',
    timezone: 'UTC',
    venue_name: '',
    venue_address: '',
    online_link: '',
    capacity: null,
});

const submit = () => {
    form.post(route('events.store'));
};
</script>
