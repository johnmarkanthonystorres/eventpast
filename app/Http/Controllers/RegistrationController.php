<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Attendee;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Referral;
use App\Notifications\RegistrationConfirmed;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function show(Request $request, Tenant $tenant, Event $event): Response
    {
        $event->load('ticketTypes');
        
        // Track referral click
        $referralCode = $request->query('ref');
        if ($referralCode) {
            $referral = Referral::where('code', $referralCode)->first();
            if ($referral) {
                $referral->incrementClicks();
            }
        }
        
        return Inertia::render('Events/Register', [
            'event' => $event,
            'tenant' => $event->tenant,
            'referralCode' => $referralCode,
        ]);
    }

    public function store(Request $request, Tenant $tenant, Event $event, PaymentService $payments)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'marketing_consent' => 'boolean',
            'referral_code' => 'nullable|string',
        ]);
        
        // Check if event can accept registrations
        if ($event->status !== 'published') {
            return back()->with('error', 'This event is not accepting registrations');
        }
        
        $ticketType = $event->ticketTypes()->findOrFail($validated['ticket_type_id']);
        
        if (!$ticketType->isAvailableForSale()) {
            return back()->with('error', 'This ticket type is not available');
        }

        if (!$ticketType->is_free && !$event->tenant->canAcceptPayments()) {
            return back()->with('error', 'Paid tickets are not available for this event right now.');
        }

        $order = null;
        
        try {
            DB::beginTransaction();

            // Serialize registrations per event so two buyers cannot both
            // take the last seat.
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            
            // Find or create attendee
            $attendee = Attendee::firstOrCreate(
                [
                    'tenant_id' => $event->tenant_id,
                    'email' => $validated['email'],
                ],
                [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'] ?? null,
                    'marketing_consent' => $validated['marketing_consent'] ?? false,
                ]
            );
            
            // Determine registration status
            // Free tickets confirm immediately; paid ones hold a seat until
            // Stripe confirms payment. A full event waitlists either way.
            $status = $ticketType->is_free ? 'confirmed' : 'pending_payment';
            if ($event->is_full) {
                $status = 'waitlisted';
            }
            
            // Track referral source. For paid tickets the conversion only
            // counts once payment succeeds (see PaymentService).
            $source = null;
            if (!empty($validated['referral_code'])) {
                $referral = Referral::where('code', $validated['referral_code'])->first();
                if ($referral) {
                    if ($status !== 'pending_payment') {
                        $referral->incrementConversions();
                    }
                    $source = 'referral:' . $validated['referral_code'];
                }
            }
            
            // Create registration
            $registration = Registration::create([
                'event_id' => $event->id,
                'attendee_id' => $attendee->id,
                'ticket_type_id' => $ticketType->id,
                'status' => $status,
                'source' => $source,
            ]);
            
            // Increment ticket sold count
            $ticketType->increment('quantity_sold');

            if ($status === 'pending_payment') {
                $order = $payments->createOrder($registration, $ticketType, $event->tenant);
            }
            
            // Create referral link for this registration
            Referral::create([
                'registration_id' => $registration->id,
            ]);
            
            DB::commit();

            if ($status === 'pending_payment') {
                return $this->startPayment($payments, $registration, $order);
            }
            
            // Send confirmation email
            $attendee->notify(new RegistrationConfirmed($registration));
            
            return redirect()->route('registrations.confirmation', $registration)
                ->with('success', $status === 'confirmed' 
                    ? 'Registration confirmed! Check your email for details.' 
                    : 'You\'ve been added to the waitlist. We\'ll notify you if a spot opens up.');
                    
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Registration failed. Please try again.');
        }
    }

    public function confirmation(Registration $registration): Response
    {
        $registration->load(['event.tenant', 'attendee', 'ticketType', 'referral']);
        
        return Inertia::render('Registrations/Confirmation', [
            'registration' => $registration,
        ]);
    }

    /**
     * Send the buyer to Stripe Checkout. If Stripe can't be reached, give the
     * seat straight back rather than leaving it held for nothing.
     */
    private function startPayment(PaymentService $payments, Registration $registration, $order)
    {
        try {
            $url = $payments->createCheckout($registration);
        } catch (\Throwable $e) {
            Log::error('Could not start Stripe checkout: '.$e->getMessage(), ['registration_id' => $registration->id]);
            $payments->release($order);

            return back()->with('error', 'We couldn\'t start the payment. Please try again in a moment.');
        }

        return Inertia::location($url);
    }

    public function qrcode(Registration $registration)
    {
        // Generate QR code as SVG
        $qrCode = \QrCode::size(300)
            ->margin(2)
            ->generate($registration->qr_token);
        
        return response($qrCode)
            ->header('Content-Type', 'image/svg+xml');
    }
}
