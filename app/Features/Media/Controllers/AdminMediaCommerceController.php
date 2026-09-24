<?php

namespace App\Features\Media\Controllers;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Services\CustomerHelpCentre;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaDiscountCode;
use App\Features\Media\Models\MediaDiscountRedemption;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Media\Requests\AssignMediaPriceListRequest;
use App\Features\Media\Requests\SaveMediaCommerceSettingsRequest;
use App\Features\Media\Requests\SaveMediaDiscountCodeRequest;
use App\Features\Media\Requests\SaveMediaPriceListRequest;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Payments\Requests\GrantCustomerCreditRequest;
use App\Features\Payments\Requests\SendMediaNotificationTestRequest;
use App\Features\Payments\Services\CustomerCredits;
use App\Features\Payments\Services\CustomerMediaNotifications;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Tracking\Services\EmailBranding;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminMediaCommerceController extends Controller
{
    public function index(Request $request, MediaCommerceSettings $settings, MediaPriceLists $priceLists, CustomerCredits $credits, CustomerHelpCentre $helpCentre, EmailBranding $branding): Response
    {
        $activeTab = in_array($request->query('tab'), ['pricing', 'discounts', 'watermarks', 'notifications', 'help'], true)
            ? $request->query('tab')
            : 'pricing';

        $customerLookup = trim((string) $request->query('customer_lookup'));

        return response()->view('admin.media.settings', [
            'settings' => $settings->all(),
            'codes' => MediaDiscountCode::query()->withCount('redemptions')->latest()->get(),
            'watermarks' => $this->watermarkQuery()->where('is_active', true)->orderBy('gallery_type')->orderBy('name')->get(),
            'archivedWatermarks' => $this->watermarkQuery()->where('is_active', false)->latest('updated_at')->get(),
            'notificationHistory' => $activeTab === 'notifications'
                ? CustomerMediaNotification::query()->with('order')->latest('queued_at')->limit(30)->get()
                : collect(),
            'activeTab' => $activeTab,
            'priceLists' => $priceLists->all(),
            'competitionPriceTargets' => SchedulingEvent::query()->where('event_type', 'competition')
                ->with('priceList')->latest('event_date')->limit(100)->get(),
            'concertPriceTargets' => Concert::query()->with('priceList')->latest('event_date')->limit(100)->get(),
            'collectionPriceTargets' => MediaCollection::query()
                ->where(fn ($query) => $query->whereNotNull('competition_id')->orWhereNotNull('concert_id'))
                ->with('priceList', 'concert')->latest()->limit(150)->get(),
            'routinePriceTargets' => ProgrammeNode::query()->where('kind', 'item')->where('is_active', true)
                ->with('priceList', 'programme.event')->latest()->limit(200)->get(),
            'customerLookup' => $customerLookup,
            'creditCustomers' => $activeTab === 'discounts' ? $credits->search($customerLookup) : collect(),
            'helpInsights' => $activeTab === 'help' ? $helpCentre->adminInsights() : ['unanswered' => collect(), 'articleFeedback' => collect()],
            'emailBranding' => $activeTab === 'notifications' ? $branding->for($request->user()->id) : [],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function storePriceList(SaveMediaPriceListRequest $request, MediaPriceLists $priceLists): RedirectResponse
    {
        $priceLists->save(null, $request->user(), $request->validated());

        return to_route('admin.media-settings.index', ['tab' => 'pricing'])->with('status', 'Price list created.');
    }

    public function updatePriceList(SaveMediaPriceListRequest $request, MediaPriceList $priceList, MediaPriceLists $priceLists): RedirectResponse
    {
        $priceLists->save($priceList, $request->user(), $request->validated());

        return to_route('admin.media-settings.index', ['tab' => 'pricing'])->with('status', 'Price list updated.');
    }

    public function assignPriceList(AssignMediaPriceListRequest $request, MediaPriceLists $priceLists): RedirectResponse
    {
        $priceList = $request->validated('media_price_list_id')
            ? MediaPriceList::query()->findOrFail($request->validated('media_price_list_id'))
            : null;
        $priceLists->assign($request->validated('target'), $priceList);

        return to_route('admin.media-settings.index', ['tab' => 'pricing'])->with('status', 'Price list assignment updated.');
    }

    public function update(SaveMediaCommerceSettingsRequest $request, MediaCommerceSettings $settings): RedirectResponse
    {
        $input = $request->validated();
        $section = $input['section'];
        unset($input['section']);
        if ($section === 'prices') {
            $input = array_intersect_key($input, array_flip(['competition_photo', 'competition_routine_photos', 'competition_routine_video', 'competition_routine_package', 'concert_photo']));
            $input = array_map(fn ($amount) => (int) round((float) $amount * 100), $input);
            if ($input['competition_routine_package'] <= $input['competition_routine_video']) {
                throw ValidationException::withMessages(['competition_routine_package' => 'The package price must exceed its video component.']);
            }
        } elseif ($section === 'discounts') {
            $input = array_intersect_key($input, array_flip(array_merge(
                array_map(fn ($n) => "bulk_{$n}_threshold", range(1, 4)),
                array_map(fn ($n) => "bulk_{$n}_percent", range(1, 4)),
            )));
            foreach (range(1, 4) as $tier) {
                $input["bulk_{$tier}_threshold"] = (int) round((float) $input["bulk_{$tier}_threshold"] * 100);
            }
            $previousThreshold = 0;
            $previousPercent = 0;
            foreach (range(1, 4) as $tier) {
                if ($input["bulk_{$tier}_threshold"] <= $previousThreshold || $input["bulk_{$tier}_percent"] < $previousPercent) {
                    throw ValidationException::withMessages(['discounts' => 'Discount thresholds must increase and percentages must not decrease.']);
                }
                $previousThreshold = $input["bulk_{$tier}_threshold"];
                $previousPercent = $input["bulk_{$tier}_percent"];
            }
            $input['upsell_enabled'] = $request->boolean('upsell_enabled');
        } elseif ($section === 'watermarks') {
            $input = ['watermark_text' => $input['watermark_text']];
            if ($request->hasFile('competition_watermark')) {
                $input['competition_watermark_path'] = $request->file('competition_watermark')->store('media-watermarks', 'local');
            }
        } else {
            if ($section === 'help') {
                $keys = ['help_content'];
            } else {
                $template = $input['notification_template'];
                unset($input['notification_template']);
                $keys = [$template.'_email_subject', $template.'_email_body'];
            }
            $input = array_intersect_key($input, array_flip($keys));
        }
        $settings->save($input);

        return back()->with('status', ucfirst($section).' saved.');
    }

    public function sendNotificationTest(SendMediaNotificationTestRequest $request, CustomerMediaNotifications $notifications): RedirectResponse
    {
        $delivery = $notifications->sendTest(
            $request->user(),
            $request->validated('template'),
            $request->validated('email'),
        );

        return to_route('admin.media-settings.index', ['tab' => 'notifications'])
            ->with('status', 'Test email sent to '.$delivery->recipient_email.'.');
    }

    public function storeCode(SaveMediaDiscountCodeRequest $request): RedirectResponse
    {
        MediaDiscountCode::query()->create($this->discountCodeValues($request));

        return back()->with('status', $request->boolean('enabled') ? 'Discount code created and activated.' : 'Discount code saved as a draft.');
    }

    public function updateCode(SaveMediaDiscountCodeRequest $request, MediaDiscountCode $discountCode): RedirectResponse
    {
        $discountCode->update($this->discountCodeValues($request));

        return back()->with('status', $request->boolean('enabled') ? 'Discount code updated and active.' : 'Discount code updated and inactive.');
    }

    public function deleteCode(MediaDiscountCode $discountCode): RedirectResponse
    {
        Gate::authorize('manageScheduling');
        if (MediaDiscountRedemption::query()->where('media_discount_code_id', $discountCode->id)->exists()) {
            throw ValidationException::withMessages(['discount_code' => 'A code used by a paid order cannot be removed. Make it inactive instead.']);
        }
        $discountCode->delete();

        return back()->with('status', 'Discount code removed.');
    }

    public function grantCustomerCredit(GrantCustomerCreditRequest $request, CustomerCredits $credits): RedirectResponse
    {
        $customer = User::query()->findOrFail($request->integer('user_id'));
        $credits->grant(
            $customer,
            (int) round((float) $request->validated('amount') * 100),
            $request->user(),
            $request->validated('note'),
            $request->validated('credit_type'),
        );

        return to_route('admin.media-settings.index', [
            'tab' => 'discounts',
            'customer_lookup' => $customer->email,
        ])->with('status', '$'.number_format((float) $request->validated('amount'), 2).' credit added to '.$customer->email.'.');
    }

    public function watermark(MediaCommerceSettings $settings): Response
    {
        $path = $settings->get('competition_watermark_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    private function discountCodeValues(SaveMediaDiscountCodeRequest $request): array
    {
        $values = $request->validated() + ['enabled' => false];
        $values['enabled'] = $request->boolean('enabled');
        $discountValue = $values['discount_value'];
        unset($values['discount_value']);
        if ($values['discount_type'] === 'fixed') {
            $values['amount'] = (int) round((float) $discountValue * 100);
            $values['percent'] = null;
        } else {
            $values['percent'] = (int) $discountValue;
            $values['amount'] = null;
        }

        return $values;
    }

    private function watermarkQuery(): Builder
    {
        return MediaWatermark::query()->whereIn('gallery_type', ['competition', 'concert'])->withCount([
            'competitionGallerySettings',
            'collections',
            'concertReleaseGroups',
        ]);
    }
}
