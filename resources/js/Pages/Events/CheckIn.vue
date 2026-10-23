<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import jsQR from 'jsqr';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
    registrations: Object,
    stats: Object,
    filters: Object,
});

// ---- Stats ---------------------------------------------------------------
const percent = computed(() =>
    props.stats.total ? Math.round((props.stats.checked_in / props.stats.total) * 100) : 0
);

// ---- Camera scanner ------------------------------------------------------
const video = ref(null);
const canvas = ref(null);
const scanning = ref(false);
const cameraError = ref('');
let stream = null;
let rafId = null;
let lastTick = 0;
let lastToken = '';
let lastTokenAt = 0;
let busy = false;

const SCAN_INTERVAL_MS = 150;
const SAME_CODE_COOLDOWN_MS = 4000;

async function startCamera() {
    cameraError.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value =
            'Camera is not available here. Camera access needs HTTPS (or localhost). You can still check people in from the list below.';
        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        video.value.srcObject = stream;
        await video.value.play();
        scanning.value = true;
        rafId = requestAnimationFrame(tick);
    } catch (e) {
        stopCamera();
        cameraError.value =
            e?.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access in your browser settings, or use the list below.'
                : 'Could not start the camera. You can still check people in from the list below.';
    }
}

function stopCamera() {
    scanning.value = false;
    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;
    if (stream) stream.getTracks().forEach((t) => t.stop());
    stream = null;
}

function tick(now) {
    if (!scanning.value) return;
    rafId = requestAnimationFrame(tick);

    const v = video.value;
    if (now - lastTick < SCAN_INTERVAL_MS || !v || v.readyState < 2 || !v.videoWidth) return;
    lastTick = now;

    const c = canvas.value;
    c.width = v.videoWidth;
    c.height = v.videoHeight;
    const ctx = c.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(v, 0, 0, c.width, c.height);

    const image = ctx.getImageData(0, 0, c.width, c.height);
    const code = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

    if (code?.data) handleToken(code.data.trim());
}

function handleToken(token) {
    const now = Date.now();
    // The same QR stays in frame for a while; don't re-submit it every frame.
    if (token === lastToken && now - lastTokenAt < SAME_CODE_COOLDOWN_MS) return;
    lastToken = token;
    lastTokenAt = now;
    submit(token);
}

// ---- Check-in request ----------------------------------------------------
const result = ref(null);
const pendingId = ref(null);
let clearTimer = null;

const palette = {
    valid: 'bg-green-50 border-green-400 text-green-900',
    duplicate: 'bg-amber-50 border-amber-400 text-amber-900',
};
const resultClass = computed(() =>
    result.value ? palette[result.value.result] || 'bg-red-50 border-red-400 text-red-900' : ''
);

async function submit(token, registrationId = null) {
    if (busy) return;
    busy = true;
    pendingId.value = registrationId;

    let data;
    try {
        const response = await axios.post(route('checkin.scan'), { qr_token: token });
        data = response.data;
    } catch (e) {
        data = e.response?.data || { success: false, result: 'error', message: 'Network error. Try again.' };
    } finally {
        busy = false;
        pendingId.value = null;
    }

    showResult(data);

    if (data.success) {
        if (navigator.vibrate) navigator.vibrate(80);
        router.reload({ only: ['registrations', 'stats'], preserveScroll: true });
    }
}

function showResult(data) {
    result.value = {
        result: data.result,
        message: data.message,
        name: data.registration?.attendee?.name || '',
        ticket: data.registration?.ticket_type?.name || '',
        at: data.check_in?.scanned_at || null,
    };
    clearTimeout(clearTimer);
    clearTimer = setTimeout(() => (result.value = null), 6000);
}

function manualCheckIn(registration) {
    submit(registration.qr_token, registration.id);
}

// ---- Search + list -------------------------------------------------------
const search = ref(props.filters?.search || '');
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('events.checkin', props.event.id),
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true, only: ['registrations', 'filters'] }
        );
    }, 300);
});

function timeOf(value) {
    return value ? new Date(value).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
}

onBeforeUnmount(() => {
    stopCamera();
    clearTimeout(clearTimer);
    clearTimeout(searchTimer);
});
</script>

<template>
    <Head :title="`Check-in · ${event.title}`" />

    <AppLayout>
        <div class="max-w-5xl mx-auto">
            <div class="mb-6">
                <Link :href="route('events.show', event.id)" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to event
                </Link>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Check-in: {{ event.title }}</h1>
            </div>

            <!-- Stats -->
            <div class="bg-white shadow rounded-lg p-5 mb-6">
                <div class="flex items-baseline justify-between">
                    <p class="text-3xl font-bold text-gray-900">
                        {{ stats.checked_in }}
                        <span class="text-lg font-medium text-gray-500">/ {{ stats.total }} checked in</span>
                    </p>
                    <p class="text-sm font-semibold text-gray-600">{{ percent }}%</p>
                </div>
                <div class="mt-3 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full bg-green-500 transition-all" :style="{ width: percent + '%' }"></div>
                </div>
            </div>

            <!-- Result banner -->
            <div
                v-if="result"
                role="status"
                aria-live="polite"
                class="mb-6 border-l-4 rounded-md p-4"
                :class="resultClass"
            >
                <p class="font-semibold">{{ result.message }}</p>
                <p v-if="result.name" class="text-sm mt-0.5">
                    {{ result.name }}<span v-if="result.ticket"> · {{ result.ticket }}</span>
                    <span v-if="result.result === 'duplicate' && result.at"> · at {{ timeOf(result.at) }}</span>
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Scanner -->
                <div class="lg:col-span-2">
                    <div class="bg-white shadow rounded-lg p-5">
                        <h2 class="text-sm font-semibold text-gray-900 mb-3">Scan QR ticket</h2>

                        <div class="relative aspect-square bg-gray-900 rounded-md overflow-hidden">
                            <video
                                ref="video"
                                class="w-full h-full object-cover"
                                playsinline
                                muted
                            ></video>
                            <div
                                v-if="!scanning"
                                class="absolute inset-0 flex items-center justify-center text-sm text-gray-300 px-6 text-center"
                            >
                                Camera is off
                            </div>
                            <div
                                v-else
                                class="absolute inset-8 border-2 border-white/70 rounded-lg pointer-events-none"
                            ></div>
                        </div>
                        <canvas ref="canvas" class="hidden"></canvas>

                        <p v-if="cameraError" class="mt-3 text-sm text-red-600">{{ cameraError }}</p>

                        <button
                            v-if="!scanning"
                            type="button"
                            @click="startCamera"
                            class="mt-4 w-full px-4 py-2 bg-blue-600 rounded-md text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Start camera
                        </button>
                        <button
                            v-else
                            type="button"
                            @click="stopCamera"
                            class="mt-4 w-full px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Stop camera
                        </button>
                    </div>
                </div>

                <!-- Attendee list -->
                <div class="lg:col-span-3">
                    <div class="bg-white shadow rounded-lg">
                        <div class="p-5 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900 mb-3">Attendees</h2>
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Search by name or email"
                                class="w-full rounded-md border-gray-300 text-sm"
                            />
                        </div>

                        <ul class="divide-y divide-gray-100">
                            <li
                                v-for="r in registrations.data"
                                :key="r.id"
                                class="px-5 py-3 flex items-center justify-between gap-4"
                            >
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ r.attendee?.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">
                                        {{ r.attendee?.email }}
                                        <span v-if="r.ticket_type"> · {{ r.ticket_type.name }}</span>
                                    </p>
                                </div>

                                <span
                                    v-if="r.check_in"
                                    class="shrink-0 px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800"
                                >
                                    In {{ timeOf(r.check_in.scanned_at) }}
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    :disabled="pendingId === r.id"
                                    @click="manualCheckIn(r)"
                                    class="shrink-0 px-3 py-1.5 bg-blue-600 rounded-md text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ pendingId === r.id ? 'Checking in…' : 'Check in' }}
                                </button>
                            </li>

                            <li v-if="!registrations.data.length" class="px-5 py-10 text-center text-sm text-gray-500">
                                {{ search ? 'No attendees match your search.' : 'No confirmed registrations yet.' }}
                            </li>
                        </ul>

                        <div
                            v-if="registrations.links && registrations.links.length > 3"
                            class="p-4 border-t border-gray-100 flex flex-wrap justify-center gap-1"
                        >
                            <template v-for="link in registrations.links" :key="link.label">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    preserve-state
                                    class="px-3 py-1 text-sm rounded-md border"
                                    :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="px-3 py-1 text-sm rounded-md border border-gray-200 text-gray-400"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
