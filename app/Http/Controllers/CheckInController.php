<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function index(Event $event): Response
    {
        $this->authorize('view', $event);
        
        $checkIns = $event->registrations()
            ->with(['attendee', 'checkIn.scanner'])
            ->where('status', 'confirmed')
            ->latest()
            ->paginate(50);
        
        $stats = [
            'total' => $event->registrations()->where('status', 'confirmed')->count(),
            'checked_in' => $event->registrations()->whereHas('checkIn')->count(),
        ];
        
        return Inertia::render('Events/CheckIn', [
            'event' => $event,
            'registrations' => $checkIns,
            'stats' => $stats,
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'qr_token' => 'required|string',
        ]);
        
        $registration = Registration::where('qr_token', $validated['qr_token'])
            ->with(['event', 'attendee', 'ticketType', 'checkIn'])
            ->first();
        
        if (!$registration) {
            return response()->json([
                'success' => false,
                'result' => 'invalid',
                'message' => 'QR code not found',
            ], 404);
        }
        
        // Verify user can check in for this event
        if (!$request->user()->canManageEvent($registration->event)) {
            return response()->json([
                'success' => false,
                'result' => 'unauthorized',
                'message' => 'You cannot check in attendees for this event',
            ], 403);
        }
        
        // Check if already checked in
        if ($registration->isCheckedIn()) {
            return response()->json([
                'success' => false,
                'result' => 'duplicate',
                'message' => 'Already checked in',
                'registration' => $registration,
                'check_in' => $registration->checkIn,
            ]);
        }
        
        // Validate registration status
        if ($registration->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'result' => 'cancelled',
                'message' => 'Registration cancelled',
                'registration' => $registration,
            ]);
        }
        
        if ($registration->status === 'waitlisted') {
            return response()->json([
                'success' => false,
                'result' => 'waitlisted',
                'message' => 'Still on waitlist',
                'registration' => $registration,
            ]);
        }
        
        if ($registration->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'result' => 'invalid_status',
                'message' => 'Invalid registration status',
                'registration' => $registration,
            ]);
        }
        
        // Check if ticket is paid (if applicable)
        if (!$registration->ticketType->is_free && $registration->order) {
            if ($registration->order->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'result' => 'payment_pending',
                    'message' => 'Payment not completed',
                    'registration' => $registration,
                ]);
            }
        }
        
        try {
            DB::beginTransaction();
            
            // Create check-in record
            $checkIn = CheckIn::create([
                'registration_id' => $registration->id,
                'scanned_by' => $request->user()->id,
                'scanned_at' => now(),
            ]);
            
            // Update registration status
            $registration->update(['status' => 'checked_in']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'result' => 'valid',
                'message' => 'Check-in successful',
                'registration' => $registration->load('checkIn.scanner'),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'result' => 'error',
                'message' => 'Check-in failed. Please try again.',
            ], 500);
        }
    }
}
