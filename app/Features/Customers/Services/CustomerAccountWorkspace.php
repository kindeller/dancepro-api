<?php

namespace App\Features\Customers\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Concerts\Models\ConcertAccess;
use App\Features\Concerts\Models\ConcertAccessGrant;
use App\Features\Customers\Models\CustomerAccountActivity;
use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerConsentHistory;
use App\Features\Customers\Models\CustomerDancer;
use App\Features\Customers\Models\CustomerEngagementEvent;
use App\Features\Customers\Models\CustomerLoginEvent;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Models\CustomerPrivacyRequest;
use App\Features\Customers\Models\CustomerRiskEvent;
use App\Features\Customers\Models\CustomerStudio;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaAssetDownload;
use App\Features\Orders\Models\OrderRefund;
use App\Features\Payments\Models\CustomerCreditTransaction;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;

class CustomerAccountWorkspace
{
    public function __construct(
        private readonly CustomerAccountMediaSelections $selections,
        private readonly CustomerCredits $credits,
        private readonly CustomerAccountIntelligence $intelligence,
    ) {}

    public function build(User $customer, array $filters): array
    {
        $tabs = ['overview', 'orders', 'credit', 'settings', 'access', 'communications', 'downloads', 'favourites', 'cart', 'insights', 'security', 'notes', 'privacy', 'activity'];
        $activeTab = in_array($filters['tab'] ?? '', $tabs, true) ? $filters['tab'] : 'overview';
        $customer->load('customerProfile');
        $data = compact('customer', 'activeTab') + ['historyPages' => [], 'creditBalance' => $this->credits->balance($customer)];
        $page = function (string $name, $query, string $label) use (&$data): void {
            $paginator = $query->paginate(25, ['*'], $name.'_page')->withQueryString();
            $data[$name] = $paginator->getCollection();
            $data['historyPages'][$label] = $paginator;
        };
        if ($activeTab === 'overview') {
            $data['dancers'] = CustomerDancer::where('user_id', $customer->id)->orderBy('name')->get();
            $data['studios'] = CustomerStudio::where('user_id', $customer->id)->with('studio')->get();
            $data['overviewCounts'] = [
                'orders' => Order::where('user_id', $customer->id)->count(),
                'access' => CustomerCompetitionRegistration::where('user_id', $customer->id)->count() + ConcertAccessGrant::where('user_id', $customer->id)->count(),
                'downloads' => OrderMediaAssetDownload::where('user_id', $customer->id)->sum('download_count'),
                'emails' => CustomerMediaNotification::where('user_id', $customer->id)->count() + CustomerAccountEmail::where('user_id', $customer->id)->count(),
                'favourites' => $this->selectionCount($customer, 'is_favourite'),
                'cart' => $this->selectionCount($customer, 'in_cart'),
            ];
        }
        if (in_array($activeTab, ['orders', 'insights'], true)) {
            $page('orders', Order::where('user_id', $customer->id)->with('refunds.items.orderItem')->latest(), 'Orders');
        }
        if ($activeTab === 'credit') {
            $page('creditTransactions', CustomerCreditTransaction::where('user_id', $customer->id)->with('createdBy', 'order')->latest(), 'Credit transactions');
        }
        if (in_array($activeTab, ['communications', 'insights'], true)) {
            $page('supportRequests', CustomerSupportRequest::where('user_id', $customer->id)->latest('requested_at'), 'Help messages');
        }
        if ($activeTab === 'communications') {
            $page('notifications', CustomerMediaNotification::where('user_id', $customer->id)->latest('queued_at'), 'Automatic notifications');
            $page('accountEmails', CustomerAccountEmail::where('user_id', $customer->id)->latest('queued_at'), 'Direct emails');
        }
        if ($activeTab === 'downloads') {
            $page('downloads', OrderMediaAssetDownload::where('user_id', $customer->id)->with('asset', 'entitlement.orderItem.order')->latest('last_download_at'), 'Downloads');
        }
        if ($activeTab === 'access') {
            $page('competitionAccess', CustomerCompetitionRegistration::where('user_id', $customer->id)->with('event')->latest(), 'Competition access');
            $page('concertGrants', ConcertAccessGrant::where('user_id', $customer->id)->with('concert')->latest(), 'Concert grants');
            $page('concertAccess', ConcertAccess::where('user_id', $customer->id)->with('concert')->latest('accessed_at'), 'Recorded access');
            $data['eventOptions'] = SchedulingEvent::where('event_type', 'competition')->whereNull('cancelled_at')->latest('event_date')->get();
            $data['concertOptions'] = Concert::latest('event_date')->get();
        }
        if ($activeTab === 'activity') {
            $page('activity', CustomerAccountActivity::where('user_id', $customer->id)->with('actor')->latest(), 'Administrative activity');
        }
        if ($activeTab === 'insights') {
            $data['metrics'] = $this->intelligence->metrics($customer);
            $data['supportOutcomes'] = CustomerSupportRequest::where('user_id', $customer->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['refundCount'] = OrderRefund::whereHas('order', fn ($q) => $q->where('user_id', $customer->id))->count();
            $page('engagement', CustomerEngagementEvent::where('user_id', $customer->id)->selectRaw('event_kind,event_uuid,action,count(*) as total,max(occurred_at) as last_at')->groupBy('event_kind', 'event_uuid', 'action')->orderByDesc('last_at'), 'Event activity');
        }
        if ($activeTab === 'security') {
            $page('loginEvents', CustomerLoginEvent::where('user_id', $customer->id)->latest('occurred_at'), 'Login history');
            $page('riskEvents', CustomerRiskEvent::where('user_id', $customer->id)->latest('occurred_at'), 'Risk history');
        }
        if ($activeTab === 'notes') {
            $page('notes', CustomerAccountNote::where('user_id', $customer->id)->with('createdBy')->latest(), 'Notes');
        }
        if ($activeTab === 'privacy') {
            $data['duplicates'] = $this->intelligence->duplicates($customer);
            $page('consentHistory', CustomerConsentHistory::where('user_id', $customer->id)->latest(), 'Consent history');
            $page('privacyRequests', CustomerPrivacyRequest::where('user_id', $customer->id)->latest('requested_at'), 'Privacy requests');
        }
        if (in_array($activeTab, ['favourites', 'cart'], true)) {
            $selectionData = $this->selections->forUser($customer, $activeTab);
            if (! empty($filters['event'])) {
                $selectionData['groups'] = $selectionData['groups']->filter(fn ($group) => $group['event']->uuid === $filters['event'])->values();
            }
            $data[$activeTab] = $selectionData;
        }

        return $data;
    }

    private function selectionCount(User $customer, string $flag): int
    {
        return CustomerMediaSelection::where('user_id', $customer->id)->where($flag, true)->count()
            + CustomerConcertPhotoSelection::where('user_id', $customer->id)->where($flag, true)->count();
    }
}
