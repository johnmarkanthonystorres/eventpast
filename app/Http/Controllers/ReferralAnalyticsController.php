<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Registration;
use App\Models\Referral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralAnalyticsController extends Controller
{
    /**
     * Display referral analytics for an event
     */
    public function show(Event $event): Response
    {
        $this->authorize('view', $event);
        
        // Get all registrations with referrals for this event
        $registrations = $event->registrations()
            ->with(['attendee', 'referral'])
            ->whereHas('referral')
            ->get();
        
        // Calculate overall stats
        $totalReferrals = $registrations->count();
        $totalClicks = $registrations->sum(fn($r) => $r->referral->clicks ?? 0);
        $totalConversions = $registrations->sum(fn($r) => $r->referral->conversions ?? 0);
        $avgConversionRate = $totalClicks > 0 ? ($totalConversions / $totalClicks) * 100 : 0;
        
        // Top referrers (attendees whose referral links generated the most conversions)
        $topReferrers = $registrations
            ->sortByDesc(fn($r) => $r->referral->conversions ?? 0)
            ->take(10)
            ->map(fn($r) => [
                'attendee' => $r->attendee,
                'clicks' => $r->referral->clicks ?? 0,
                'conversions' => $r->referral->conversions ?? 0,
                'conversion_rate' => $r->referral->conversion_rate ?? 0,
            ]);
        
        // Referral sources (track where conversions came from)
        $referralSources = Registration::where('event_id', $event->id)
            ->where('source', 'like', 'referral:%')
            ->get()
            ->groupBy('source')
            ->map(fn($group) => [
                'code' => str_replace('referral:', '', $group->first()->source),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();
        
        // Daily referral activity (last 30 days)
        $dailyActivity = Registration::where('event_id', $event->id)
            ->where('source', 'like', 'referral:%')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        return Inertia::render('Events/ReferralAnalytics', [
            'event' => $event,
            'stats' => [
                'total_referrals' => $totalReferrals,
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'avg_conversion_rate' => round($avgConversionRate, 2),
                'registrations_from_referrals' => $totalConversions,
                'total_registrations' => $event->registrations()->count(),
                'referral_percentage' => $event->registrations()->count() > 0 
                    ? round(($totalConversions / $event->registrations()->count()) * 100, 1)
                    : 0,
            ],
            'topReferrers' => $topReferrers,
            'referralSources' => $referralSources,
            'dailyActivity' => $dailyActivity,
        ]);
    }
    
    /**
     * Get referral leaderboard for all tenant events
     */
    public function leaderboard(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        
        // Get all registrations with referrals across all tenant events
        $allReferrals = Registration::whereHas('event', function($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })
        ->with(['attendee', 'referral', 'event'])
        ->whereHas('referral')
        ->get();
        
        // Build leaderboard
        $leaderboard = $allReferrals
            ->sortByDesc(fn($r) => $r->referral->conversions ?? 0)
            ->take(50)
            ->map(fn($r) => [
                'attendee' => $r->attendee,
                'event' => $r->event,
                'clicks' => $r->referral->clicks ?? 0,
                'conversions' => $r->referral->conversions ?? 0,
                'conversion_rate' => $r->referral->conversion_rate ?? 0,
            ]);
        
        // Overall tenant stats
        $totalClicks = $allReferrals->sum(fn($r) => $r->referral->clicks ?? 0);
        $totalConversions = $allReferrals->sum(fn($r) => $r->referral->conversions ?? 0);
        
        return Inertia::render('Analytics/ReferralLeaderboard', [
            'leaderboard' => $leaderboard,
            'stats' => [
                'total_referrers' => $allReferrals->count(),
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'avg_conversion_rate' => $totalClicks > 0 
                    ? round(($totalConversions / $totalClicks) * 100, 2)
                    : 0,
            ],
        ]);
    }
}
