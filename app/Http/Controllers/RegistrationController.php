<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Attendee;
use App\Models\Registration;
use App\Models\Referral;
use App\Notifications\RegistrationConfirmed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function show(Request $request, Event $event): Response
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

    public function store(Request $request, Event $event)
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
        
        try {
            DB::beginTransaction();
            
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
            $status = 'confirmed';
            if ($event->is_full) {
                $status = 'waitlisted';
            }
            
            // Track referral source
            $source = null;
            if (!empty($validated['referral_code'])) {
                $referral = Referral::where('code', $validated['referral_code'])->first();
                if ($referral) {
                    $referral->incrementConversions();
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
            
            // Create referral link for this registration
            Referral::create([
                'registration_id' => $registration->id,
            ]);
            
            DB::commit();
            
            // Send confirmation email
            $attendee->notify(new RegistrationConfirmed($registration));
            
            return redirect()->route('registrations.confirmation', $registration->id)
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
        $registration->load(['event', 'attendee', 'ticketType', 'referral']);
        
        return Inertia::render('Registrations/Confirmation', [
            'registration' => $registration,
        ]);
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
