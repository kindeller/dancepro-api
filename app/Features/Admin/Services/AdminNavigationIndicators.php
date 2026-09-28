<?php

namespace App\Features\Admin\Services;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerFollowUps;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AdminNavigationIndicators
{
    public function __construct(
        private readonly AdminExceptionOverview $exceptions,
        private readonly CrewNavigationIndicators $crew,
        private readonly CustomerFollowUps $followUps,
    ) {}

    public function for(?User $user): array
    {
        $empty = array_fill_keys(['my_hub', 'exceptions', 'pending_events', 'hub_management', 'studio_messages', 'support_pending', 'follow_ups_due', 'customers'], 0);
        if (! $user?->canAccessAdmin()) {
            return $empty;
        }
        $cacheKey = 'admin_navigation_indicators:'.$user->id;
        if (request()->attributes->has($cacheKey)) {
            return request()->attributes->get($cacheKey);
        }

        $counts = $empty;
        if (Gate::forUser($user)->allows('manageScheduling')) {
            $counts['exceptions'] = $this->exceptions->all()->where('severity', 'action')->count();
            $counts['pending_events'] = ConcertBookingItem::awaitingApproval()->count();
            $counts['studio_messages'] = StudioConversationMessage::where('direction', 'to_dancepro')->whereNull('staff_read_at')->count();
        }
        $counts['my_hub'] = $user->crewProfile ? $this->crew->for($user)['shifts'] : 0;
        $counts['support_pending'] = CustomerSupportRequest::where('status', 'pending')->count();
        $counts['follow_ups_due'] = $this->followUps->dueCount();
        // Roll up leaf queues once, never both a child total and its parent total.
        $counts['hub_management'] = $counts['exceptions'] + $counts['pending_events'];
        $counts['customers'] = $counts['support_pending'] + $counts['follow_ups_due'];
        request()->attributes->set($cacheKey, $counts);

        return $counts;
    }
}
