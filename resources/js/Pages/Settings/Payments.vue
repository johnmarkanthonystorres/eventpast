<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stripeConfigured: Boolean,
    hasAccount: Boolean,
    chargesEnabled: Boolean,
    feePercentage: Number,
    fixedFee: Number,
    currency: String,
});

const state = computed(() => {
    if (!props.hasAccount) return 'none';
    return props.chargesEnabled ? 'ready' : 'incomplete';
});

const code = computed(() => (props.currency || '').toUpperCase());

function connect() {
    // A full-page redirect to Stripe, so use a normal POST via Inertia.
    router.post(route('settings.payments.connect'));
}
</script>

<template>
    <Head title="Payments" />

    <AppLayout>
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Payments</h1>

            <div
                v-if="!stripeConfigured"
                class="mb-6 border-l-4 border-red-400 bg-red-50 rounded-md p-4 text-sm text-red-900"
            >
                Payments aren't set up on this server yet (missing Stripe keys). Contact the platform administrator.
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Stripe account</h2>
                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full"
                        :class="{
                            'bg-green-100 text-green-800': state === 'ready',
                            'bg-amber-100 text-amber-800': state === 'incomplete',
                            'bg-gray-100 text-gray-700': state === 'none',
                        }"
                    >
                        {{ state === 'ready' ? 'Connected' : state === 'incomplete' ? 'Setup incomplete' : 'Not connected' }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-gray-600">
                    <template v-if="state === 'ready'">
                        You can sell paid tickets. Buyers pay you directly and Stripe pays out to your bank on its normal schedule.
                    </template>
                    <template v-else-if="state === 'incomplete'">
                        Stripe needs a few more details before you can take payments.
                    </template>
                    <template v-else>
                        Connect a Stripe account to sell paid tickets. Free events don't need one.
                    </template>
                </p>

                <button
                    v-if="state !== 'ready'"
                    type="button"
                    :disabled="!stripeConfigured"
                    @click="connect"
                    class="mt-5 px-4 py-2 bg-indigo-600 rounded-md text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ state === 'incomplete' ? 'Continue setup' : 'Connect with Stripe' }}
                </button>

                <Link
                    v-else
                    :href="route('settings.payments.return')"
                    class="mt-5 inline-block text-sm text-gray-600 hover:underline"
                >
                    Refresh status
                </Link>
            </div>

            <div class="bg-white shadow rounded-lg p-6 mt-6">
                <h2 class="text-lg font-semibold text-gray-900">Platform fee</h2>
                <p class="mt-2 text-sm text-gray-600">
                    On your current plan, each paid ticket carries a fee of
                    <strong>{{ feePercentage }}%</strong>
                    <template v-if="fixedFee > 0"> + {{ fixedFee.toFixed(2) }} {{ code }}</template>,
                    deducted automatically from the sale. Stripe's own processing fees are separate.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
