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
        
        $confirmedCount = $event->registrations()->where('status', 'confirmed')->count();
        $checkedInCount = $event->registrations()->whereHas('checkIn')->count();
        
        $stats = [
            'checkedIn' => $checkedInCount,
            'pending' => $confirmedCount - $checkedInCount,
            'checkInRate' => $confirmedCount > 0 ? round(($checkedInCount / $confirmedCount) * 100) : 0,
        ];
        
        return Inertia::render('CheckIn/Scanner', [
            'event' => $event,
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
                'error' => 'Invalid QR Code',
                'message' => 'This QR code was not found in our system.',
            ], 404);
        }
        
        // Verify user can check in for this event
        if (!$request->user()->canManageEvent($registration->event)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'You cannot check in attendees for this event.',
            ], 403);
        }
        
        // Check if already checked in
        if ($registration->isCheckedIn()) {
            $checkIn = $registration->checkIn;
            $timeAgo = $checkIn->created_at->diffForHumans();
            
            return response()->json([
                'success' => false,
                'error' => 'Already Checked In',
                'message' => "Already checked in {$timeAgo}",
                'attendee_name' => $registration->attendee->name,
                'attendee_email' => $registration->attendee->email,
            ], 400);
        }
        
        // Validate registration status
        if ($registration->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'error' => 'Registration Cancelled',
                'message' => 'This registration has been cancelled.',
                'attendee_name' => $registration->attendee->name,
            ], 400);
        }
        
        if ($registration->status === 'waitlisted') {
            return response()->json([
                'success' => false,
                'error' => 'On Waitlist',
                'message' => 'This attendee is still on the waitlist.',
                'attendee_name' => $registration->attendee->name,
            ], 400);
        }
        
        if ($registration->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'error' => 'Invalid Status',
                'message' => 'Registration status: ' . $registration->status,
                'attendee_name' => $registration->attendee->name,
            ], 400);
        }
        
        // Check if ticket is paid (if applicable)
        if ($registration->ticketType && !$registration->ticketType->is_free) {
            if ($registration->order && $registration->order->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment Pending',
                    'message' => 'Payment has not been completed for this ticket.',
                    'attendee_name' => $registration->attendee->name,
                ], 400);
            }
        }
        
        try {
            DB::beginTransaction();
            
            // Create check-in record
            $checkIn = CheckIn::create([
                'registration_id' => $registration->id,
                'checked_in_by' => $request->user()->name,
                'checked_in_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Welcome to ' . $registration->event->title,
                'attendee_name' => $registration->attendee->name,
                'attendee_email' => $registration->attendee->email,
                'ticket_type' => $registration->ticketType?->name,
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'error' => 'Check-in Failed',
                'message' => 'An error occurred. Please try again.',
            ], 500);
        }
    }
}
