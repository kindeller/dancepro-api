<?php

namespace App\Features\Admin\Services;

use App\Features\Bookings\Models\ConcertBookingItem;
use App\Features\Crew\Services\CrewNavigationIndicators;
use App\Features\Customers\Models\CustomerStudioRequest;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerFollowUps;
use App\Features\Customers\Services\CustomerInbox;
use App\Features\Exceptions\Services\AdminExceptionOverview;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Website\Services\WebsiteContent;
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
        $empty = array_fill_keys(['studio_requests', 'my_hub', 'exceptions', 'pending_events', 'hub_management', 'studio_messages', 'support_pending', 'follow_ups_due', 'customers', 'availability', 'my_media', 'crew_messages', 'messages', 'website_bios', 'website_management'], 0);
        if (! $user?->canAccessAdmin()) {
            return $empty;
        }
        $cacheKey = 'admin_navigation_indicators:'.$user->id;
        if (request()->attributes->has($cacheKey)) {
            return request()->attributes->get($cacheKey);
        }

        $counts = $empty;
        if (Gate::forUser($user)->allows('manageStudios')) {
            $counts['studio_requests'] = CustomerStudioRequest::where('status', 'pending')->count();
        }
        $counts['website_bios'] = app(WebsiteContent::class)->pendingBios();
        $counts['website_management'] = $counts['website_bios'];
        if (Gate::forUser($user)->allows('manageScheduling')) {
            $counts['exceptions'] = $this->exceptions->all()->where('severity', 'action')->count();
            $counts['availability'] = $this->exceptions->all()->where('severity', 'action')->where('category', 'shifts-events')->count();
            $counts['pending_events'] = ConcertBookingItem::awaitingApproval()->count();
            $counts['studio_messages'] = StudioConversationMessage::where('direction', 'to_dancepro')->whereNull('staff_read_at')->count();
        }
        $crew = $user->crewProfile ? $this->crew->for($user) : ['shifts' => 0, 'timesheets' => 0, 'chat' => 0];
        $counts['my_hub'] = $crew['shifts'] + $crew['timesheets'];
        $counts['crew_messages'] = CustomerSupportRequest::forMessageAudience('crew')->where('status', 'pending')->count();
        $counts['my_media'] = $user->canAccessCustomer() ? app(CustomerInbox::class)->counts($user)['total'] : 0;
        $counts['support_pending'] = CustomerSupportRequest::forMessageAudience('customer')->where('status', 'pending')->count();
        $counts['follow_ups_due'] = $this->followUps->dueCount();
        // Roll up leaf queues once, never both a child total and its parent total.
        $counts['hub_management'] = $counts['exceptions'] + $counts['pending_events'] + $counts['my_hub'] + $counts['studio_requests'];
        $counts['customers'] = $counts['follow_ups_due'];
        $counts['messages'] = $counts['support_pending'] + $counts['studio_messages'] + $counts['crew_messages'];
        request()->attributes->set($cacheKey, $counts);

        return $counts;
    }
}
