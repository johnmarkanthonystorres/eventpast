<template>
    <div class="min-h-screen bg-gray-900 text-white">
        <!-- Header -->
        <div class="bg-gray-800 border-b border-gray-700 px-6 py-4">
            <div class="max-w-4xl mx-auto flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">{{ event.title }}</h1>
                    <p class="text-sm text-gray-400">Check-in Scanner</p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold text-green-400">{{ checkedInCount }}</p>
                    <p class="text-xs text-gray-400">Checked In / {{ totalRegistrations }}</p>
                </div>
            </div>
        </div>

        <!-- Main Scanner Area -->
        <div class="max-w-4xl mx-auto px-6 py-8">
            <!-- Scanner Status -->
            <div class="mb-6 text-center">
                <div v-if="isScanning" class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-full">
                    <div class="w-3 h-3 bg-blue-300 rounded-full animate-pulse mr-3"></div>
                    <span class="text-sm font-medium">Scanner Active</span>
                </div>
                <div v-else class="inline-flex items-center px-4 py-2 bg-gray-700 rounded-full">
                    <div class="w-3 h-3 bg-gray-500 rounded-full mr-3"></div>
                    <span class="text-sm font-medium">Scanner Paused</span>
                </div>
            </div>

            <!-- Video Preview -->
            <div class="relative bg-black rounded-lg overflow-hidden mb-6" style="aspect-ratio: 4/3;">
                <video ref="videoElement" autoplay playsinline class="w-full h-full object-cover"></video>
                
                <!-- Scanner Overlay -->
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                    <div class="w-64 h-64 border-4 border-blue-500 rounded-lg opacity-50"></div>
                </div>

                <!-- Scan Result Overlay -->
                <div v-if="scanResult" 
                     :class="{
                         'bg-green-500': scanResult.success,
                         'bg-red-500': !scanResult.success
                     }"
                     class="absolute inset-0 flex items-center justify-center transition-opacity"
                     :style="{ opacity: scanResult.show ? 0.95 : 0 }">
                    <div class="text-center">
                        <div class="w-24 h-24 mx-auto mb-4 bg-white rounded-full flex items-center justify-center">
                            <svg v-if="scanResult.success" class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <svg v-else class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h2 class="text-3xl font-bold text-white mb-2">
                            {{ scanResult.success ? 'Welcome!' : 'Error' }}
                        </h2>
                        <p class="text-xl text-white mb-2">{{ scanResult.name }}</p>
                        <p class="text-sm text-white opacity-90">{{ scanResult.message }}</p>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex justify-center space-x-4 mb-8">
                <button
                    @click="toggleScanner"
                    :class="isScanning ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                    class="px-6 py-3 rounded-lg font-semibold transition-colors"
                >
                    {{ isScanning ? '⏸ Pause Scanner' : '▶ Start Scanner' }}
                </button>
                <Link
                    :href="route('events.show', event.id)"
                    class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-semibold transition-colors"
                >
                    ← Back to Event
                </Link>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-green-400">{{ stats.checkedIn }}</p>
                    <p class="text-xs text-gray-400">Checked In</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-blue-400">{{ stats.pending }}</p>
                    <p class="text-xs text-gray-400">Pending</p>
                </div>
                <div class="bg-gray-800 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-purple-400">{{ stats.checkInRate }}%</p>
                    <p class="text-xs text-gray-400">Check-in Rate</p>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Scans</h3>
                <div v-if="recentScans.length > 0" class="space-y-3">
                    <div v-for="scan in recentScans" :key="scan.id"
                         class="flex justify-between items-center p-3 bg-gray-700 rounded-lg">
                        <div>
                            <p class="font-medium">{{ scan.name }}</p>
                            <p class="text-sm text-gray-400">{{ scan.email }}</p>
                        </div>
                        <div class="text-right">
                            <span v-if="scan.success" class="text-green-400 text-sm font-medium">✓ Success</span>
                            <span v-else class="text-red-400 text-sm font-medium">✗ Failed</span>
                            <p class="text-xs text-gray-500">{{ scan.time }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-gray-500 py-4">No scans yet</p>
            </div>

            <!-- Instructions -->
            <div class="mt-6 bg-blue-900 bg-opacity-50 border border-blue-700 rounded-lg p-4">
                <h4 class="font-semibold mb-2">📱 Scanner Tips</h4>
                <ul class="text-sm text-gray-300 space-y-1">
                    <li>• Hold QR codes steady in the center box</li>
                    <li>• Ensure good lighting for best results</li>
                    <li>• Scanner will beep on successful scan</li>
                    <li>• Each ticket can only be scanned once</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import jsQR from 'jsqr';

const props = defineProps({
    event: Object,
    stats: Object,
});

const videoElement = ref(null);
const isScanning = ref(false);
const scanResult = ref(null);
const recentScans = ref([]);
const checkedInCount = ref(props.stats.checkedIn);
let videoStream = null;
let scanInterval = null;

const totalRegistrations = computed(() => {
    return props.stats.checkedIn + props.stats.pending;
});

const startCamera = async () => {
    try {
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        videoElement.value.srcObject = videoStream;
        isScanning.value = true;
        startScanning();
    } catch (error) {
        console.error('Camera access denied:', error);
        alert('Please allow camera access to use the scanner');
    }
};

const stopCamera = () => {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    isScanning.value = false;
};

const toggleScanner = () => {
    if (isScanning.value) {
        stopCamera();
    } else {
        startCamera();
    }
};

const startScanning = () => {
    scanInterval = setInterval(() => {
        if (!isScanning.value || !videoElement.value) return;

        const canvas = document.createElement('canvas');
        const video = videoElement.value;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height);
        
        if (code) {
            processQRCode(code.data);
        }
    }, 300); // Scan every 300ms
};

const processQRCode = async (qrToken) => {
    // Pause scanning during processing
    const wasScanning = isScanning.value;
    isScanning.value = false;

    try {
        const response = await fetch(route('checkin.scan'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ qr_token: qrToken }),
        });

        const data = await response.json();

        if (response.ok) {
            showScanResult(true, data.attendee_name, data.message);
            checkedInCount.value++;
            addRecentScan(data.attendee_name, data.attendee_email, true);
            playSuccessSound();
        } else {
            showScanResult(false, data.error || 'Unknown', data.message || 'Scan failed');
            addRecentScan(data.error || 'Unknown', '', false);
            playErrorSound();
        }
    } catch (error) {
        showScanResult(false, 'Error', 'Network error. Please try again.');
        playErrorSound();
    }

    // Resume scanning after 3 seconds
    setTimeout(() => {
        if (wasScanning) {
            isScanning.value = true;
        }
    }, 3000);
};

const showScanResult = (success, name, message) => {
    scanResult.value = { success, name, message, show: true };
    
    setTimeout(() => {
        if (scanResult.value) {
            scanResult.value.show = false;
        }
    }, 2500);
};

const addRecentScan = (name, email, success) => {
    recentScans.value.unshift({
        id: Date.now(),
        name,
        email,
        success,
        time: new Date().toLocaleTimeString(),
    });
    
    // Keep only last 10 scans
    if (recentScans.value.length > 10) {
        recentScans.value.pop();
    }
};

const playSuccessSound = () => {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUDo=');
    audio.play().catch(() => {});
};

const playErrorSound = () => {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ0PV6/n77BdGgpHmuHyxXAiBjCEzvPWgTMHHm7A7+OZUQ==');
    audio.play().catch(() => {});
};

onMounted(() => {
    // Auto-start scanner on mount
    startCamera();
});

onUnmounted(() => {
    stopCamera();
});
</script>
