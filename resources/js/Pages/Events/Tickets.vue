<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney } from '@/lib/money';

const props = defineProps({
    event: Object,
    tickets: Array,
    canManage: Boolean,
    paymentsReady: Boolean,
    currency: String,
});

const editingId = ref(null);

const form = useForm({
    name: '',
    description: '',
    price: 0,
    quantity: '',
    sales_start_at: '',
    sales_end_at: '',
    is_active: true,
});

const isEditing = computed(() => editingId.value !== null);

// <input type="datetime-local"> wants YYYY-MM-DDTHH:MM
function toInput(value) {
    return value ? String(value).replace(' ', 'T').slice(0, 16) : '';
}

function startEdit(ticket) {
    editingId.value = ticket.id;
    form.clearErrors();
    form.name = ticket.name;
    form.description = ticket.description || '';
    form.price = Number(ticket.price);
    form.quantity = ticket.quantity ?? '';
    form.sales_start_at = toInput(ticket.sales_start_at);
    form.sales_end_at = toInput(ticket.sales_end_at);
    form.is_active = !!ticket.is_active;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function reset() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    // Blank inputs must reach the server as null, not empty strings.
    form
        .transform((data) => ({
            ...data,
            quantity: data.quantity === '' ? null : data.quantity,
            sales_start_at: data.sales_start_at || null,
            sales_end_at: data.sales_end_at || null,
        }))
        [isEditing.value ? 'put' : 'post'](
            isEditing.value
                ? route('events.tickets.update', [props.event.id, editingId.value])
                : route('events.tickets.store', props.event.id),
            { preserveScroll: true, onSuccess: reset }
        );
}

function toggleActive(ticket) {
    router.put(
        route('events.tickets.update', [props.event.id, ticket.id]),
        {
            name: ticket.name,
            description: ticket.description,
            price: ticket.price,
            quantity: ticket.quantity,
            sales_start_at: ticket.sales_start_at,
            sales_end_at: ticket.sales_end_at,
            is_active: !ticket.is_active,
        },
        { preserveScroll: true }
    );
}

function remove(ticket) {
    if (!confirm(`Delete "${ticket.name}"?`)) return;
    router.delete(route('events.tickets.destroy', [props.event.id, ticket.id]), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Tickets · ${event.title}`" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <Link :href="route('events.show', event.id)" class="text-sm text-gray-500 hover:text-gray-700">
                    &larr; Back to event
                </Link>
                <h1 class="mt-1 text-2xl font-bold text-gray-900">Tickets: {{ event.title }}</h1>
            </div>

            <div
                v-if="canManage && !paymentsReady"
                class="mb-6 border-l-4 border-amber-400 bg-amber-50 rounded-md p-4 text-sm text-amber-900"
            >
                Paid tickets are off until you connect a Stripe account.
                <Link :href="route('settings.payments')" class="font-semibold underline">Set up payments</Link>
            </div>

            <!-- Form -->
            <form
                v-if="canManage"
                @submit.prevent="submit"
                class="bg-white shadow rounded-lg p-6 mb-6 space-y-4"
            >
                <h2 class="text-lg font-semibold text-gray-900">
                    {{ isEditing ? 'Edit ticket' : 'Add a ticket' }}
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border-gray-300" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Price ({{ (currency || '').toUpperCase() }}) <span class="text-gray-400">0 = free</span>
                        </label>
                        <input
                            v-model="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            class="mt-1 w-full rounded-md border-gray-300"
                            required
                        />
                        <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">{{ form.errors.price }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea v-model="form.description" rows="2" class="mt-1 w-full rounded-md border-gray-300"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Quantity <span class="text-gray-400">blank = unlimited</span>
                        </label>
                        <input v-model="form.quantity" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300" />
                        <p v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">{{ form.errors.quantity }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sales start</label>
                        <input v-model="form.sales_start_at" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sales end</label>
                        <input v-model="form.sales_end_at" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300" />
                        <p v-if="form.errors.sales_end_at" class="mt-1 text-sm text-red-600">{{ form.errors.sales_end_at }}</p>
                    </div>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                    On sale
                </label>

                <div class="flex gap-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-blue-600 rounded-md text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ isEditing ? 'Save changes' : 'Add ticket' }}
                    </button>
                    <button
                        v-if="isEditing"
                        type="button"
                        @click="reset"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                </div>
            </form>

            <!-- List -->
            <div class="bg-white shadow rounded-lg divide-y divide-gray-100">
                <div v-for="t in tickets" :key="t.id" class="p-5 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-gray-900">{{ t.name }}</p>
                            <span
                                class="px-2 py-0.5 text-xs font-semibold rounded-full"
                                :class="t.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                            >
                                {{ t.is_active ? 'On sale' : 'Off' }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mt-0.5">
                            {{ formatMoney(t.price, currency) }} ·
                            {{ t.quantity === null ? `${t.quantity_sold} sold` : `${t.quantity_sold} / ${t.quantity} sold` }}
                        </p>
                        <p v-if="t.description" class="text-sm text-gray-500 mt-1">{{ t.description }}</p>
                    </div>

                    <div v-if="canManage" class="shrink-0 flex gap-2 text-sm">
                        <button @click="startEdit(t)" class="text-blue-600 hover:underline">Edit</button>
                        <button @click="toggleActive(t)" class="text-gray-600 hover:underline">
                            {{ t.is_active ? 'Turn off' : 'Turn on' }}
                        </button>
                        <button v-if="t.quantity_sold === 0" @click="remove(t)" class="text-red-600 hover:underline">
                            Delete
                        </button>
                    </div>
                </div>

                <p v-if="!tickets.length" class="p-8 text-center text-sm text-gray-500">
                    No tickets yet. Add one above so people can register.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
