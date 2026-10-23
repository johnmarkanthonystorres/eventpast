<template>
    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Check-in Scanner</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ event.title }}</p>
                </div>
                <Link
                    :href="route('events.show', event.id)"
                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    ← Back to Event
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Checked In</div>
                    <div class="text-2xl font-bold text-green-600">{{ stats.checked_in }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Total Registered</div>
                    <div class="text-2xl font-bold text-blue-600">{{ stats.total }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500">Attendance Rate</div>
                    <div class="text-2xl font-bold text-purple-600">{{ attendanceRate }}%</div>
                </div>
            </div>

            <!-- Scanner Card -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">Scan QR Code</h2>
                    <button
                        @click="toggleScanner"
                        :class="scannerActive ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                        class="px-4 py-2 text-white rounded-md font-medium transition"
                    >
                        {{ scannerActive ? '⏸ Stop Scanner' : '▶ Start Scanner' }}
                    </button>
                </div>

                <!-- Camera View -->
                <div v-if="scannerActive" class="relative bg-gray-900 rounded-lg overflow-hidden" style="height: 400px;">
                    <video ref="videoElement" autoplay playsinline class="w-full h-full object-cover"></video>
                    
                    <!-- Scan Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="border-4 border-green-500 rounded-lg" style="width: 250px; height: 250px;">
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-green-400"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-green-400"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-green-400"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-green-400"></div>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="absolute bottom-4 left-0 right-0 text-center">
                        <p class="text-white text-sm bg-black bg-opacity-50 inline-block px-4 py-2 rounded">
                            Position QR code within the frame
                        </p>
                    </div>
                </div>

                <!-- Scanner Inactive State -->
                <div v-else class="bg-gray-100 rounded-lg p-12 text-center" style="height: 400px; display: flex; align-items: center; justify-content: center;">
                    <div>
                        <svg class="w-24 h-24 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        <p class="text-gray-600 mb-2">Click "Start Scanner" to begin checking in attendees</p>
                        <p class="text-sm text-gray-500">Make sure to allow camera access when prompted</p>
                    </div>
                </div>

                <!-- Manual Code Entry -->
                <div class="mt-4 pt-4 border-t">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Or enter code manually:</label>
                    <div class="flex space-x-2">
                        <input
                            v-model="manualCode"
                            @keyup.enter="checkInManual"
                            type="text"
                            placeholder="Paste or type QR code..."
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        />
                        <button
                            @click="checkInManual"
                            :disabled="!manualCode || processing"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Check In
                        </button>
                    </div>
                </div>
            </div>

            <!-- Result Card -->
            <transition name="fade">
                <div v-if="scanResult" class="mb-6">
                    <div
                        :class="{
                            'bg-green-50 border-green-200': scanResult.success,
                            'bg-red-50 border-red-200': !scanResult.success,
                        }"
                        class="border-2 rounded-lg p-6"
                    >
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg
                                    v-if="scanResult.success"
                                    class="h-12 w-12 text-green-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <svg
                                    v-else
                                    class="h-12 w-12 text-red-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <h3
                                    :class="{
                                        'text-green-900': scanResult.success,
                                        'text-red-900': !scanResult.success,
                                    }"
                                    class="text-lg font-semibold mb-1"
                                >
                                    {{ scanResult.message }}
                                </h3>
                                <div v-if="scanResult.registration">
                                    <p class="text-gray-700 font-medium">{{ scanResult.registration.attendee.name }}</p>
                                    <p class="text-sm text-gray-600">{{ scanResult.registration.attendee.email }}</p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Ticket: {{ scanResult.registration.ticket_type.name }}
                                    </p>
                                </div>
                                <p v-if="scanResult.details" class="text-sm text-gray-600 mt-2">
                                    {{ scanResult.details }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Recent Check-ins -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Check-ins</h2>
                
                <div v-if="recentCheckIns.length === 0" class="text-center py-8 text-gray-500">
                    No check-ins yet
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="checkIn in recentCheckIns"
                        :key="checkIn.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ checkIn.registration.attendee.name }}</p>
                                <p class="text-sm text-gray-500">{{ checkIn.registration.ticket_type.name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">{{ formatTime(checkIn.created_at) }}</p>
                            <p class="text-xs text-gray-500">Just now</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import jsQR from 'jsqr';

const props = defineProps({
    event: Object,
    stats: Object,
    recentCheckIns: Array,
});

const scannerActive = ref(false);
const videoElement = ref(null);
const manualCode = ref('');
const scanResult = ref(null);
const processing = ref(false);
const stream = ref(null);
const scanInterval = ref(null);

const attendanceRate = computed(() => {
    if (props.stats.total === 0) return 0;
    return Math.round((props.stats.checked_in / props.stats.total) * 100);
});

const toggleScanner = async () => {
    if (scannerActive.value) {
        stopScanner();
    } else {
        await startScanner();
    }
};

const startScanner = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        
        if (videoElement.value) {
            videoElement.value.srcObject = stream.value;
            scannerActive.value = true;
            startScanning();
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Unable to access camera. Please check permissions.');
    }
};

const stopScanner = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
        stream.value = null;
    }
    if (scanInterval.value) {
        clearInterval(scanInterval.value);
        scanInterval.value = null;
    }
    scannerActive.value = false;
};

const startScanning = () => {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    
    scanInterval.value = setInterval(() => {
        if (videoElement.value && videoElement.value.readyState === videoElement.value.HAVE_ENOUGH_DATA) {
            canvas.width = videoElement.value.videoWidth;
            canvas.height = videoElement.value.videoHeight;
            context.drawImage(videoElement.value, 0, 0, canvas.width, canvas.height);
            
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            
            if (code && !processing.value) {
                processCheckIn(code.data);
            }
        }
    }, 300);
};

const processCheckIn = async (qrToken) => {
    if (processing.value) return;
    
    processing.value = true;
    scanResult.value = null;
    
    try {
        const response = await axios.post(route('checkin.scan'), {
            qr_token: qrToken,
            event_id: props.event.id,
        });
        
        scanResult.value = {
            success: true,
            message: '✓ Check-in Successful!',
            registration: response.data.registration,
        };
        
        // Refresh stats
        router.reload({ only: ['stats', 'recentCheckIns'] });
        
        // Play success sound
        playSuccessSound();
        
    } catch (error) {
        const errorData = error.response?.data;
        scanResult.value = {
            success: false,
            message: errorData?.message || 'Check-in Failed',
            details: errorData?.details || 'Please try again or contact support.',
            registration: errorData?.registration || null,
        };
        
        // Play error sound
        playErrorSound();
    } finally {
        processing.value = false;
        
        // Clear result after 5 seconds
        setTimeout(() => {
            scanResult.value = null;
        }, 5000);
    }
};

const checkInManual = () => {
    if (manualCode.value && !processing.value) {
        processCheckIn(manualCode.value.trim());
        manualCode.value = '';
    }
};

const playSuccessSound = () => {
    const audio = new Audio('/sounds/success.mp3');
    audio.volume = 0.5;
    audio.play().catch(() => {});
};

const playErrorSound = () => {
    const audio = new Audio('/sounds/error.mp3');
    audio.volume = 0.5;
    audio.play().catch(() => {});
};

const formatTime = (datetime) => {
    const date = new Date(datetime);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

onUnmounted(() => {
    stopScanner();
});
</script>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
