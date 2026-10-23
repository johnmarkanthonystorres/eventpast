<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReleaseStalePayments extends Command
{
    protected $signature = 'payments:release-stale';

    protected $description = 'Release seats held by unpaid orders whose Stripe checkout has long expired';

    public function handle(PaymentService $payments): int
    {
        // Stripe expires the session at checkout_minutes and normally tells us.
        // This covers a lost webhook; the buffer avoids racing a late one.
        $cutoff = now()->subMinutes(config('services.stripe.checkout_minutes') + 15);

        $released = 0;

        Order::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->each(function (Order $order) use ($payments, &$released) {
                if ($payments->release($order)) {
                    $released++;
                }
            });

        $this->info("Released {$released} stale order(s).");

        return self::SUCCESS;
    }
}
