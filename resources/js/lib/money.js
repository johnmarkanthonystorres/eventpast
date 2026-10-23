import { usePage } from '@inertiajs/vue3';

/**
 * Format a ticket price in the platform currency (shared Inertia prop).
 * Zero is shown as "Free".
 */
export function formatMoney(amount, currency = null) {
    const n = Number(amount);
    if (!n) return 'Free';

    const code = (currency || usePage().props.currency || 'PHP').toUpperCase();

    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(n);
    } catch {
        return `${n.toFixed(2)} ${code}`;
    }
}
