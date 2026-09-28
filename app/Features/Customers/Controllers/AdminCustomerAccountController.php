<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Models\CustomerAccountNote;
use App\Features\Customers\Models\CustomerRiskEvent;
use App\Features\Customers\Requests\AddCustomerEventAccessRequest;
use App\Features\Customers\Requests\AdminCustomerPageRequest;
use App\Features\Customers\Requests\CreateCustomerRefundRequest;
use App\Features\Customers\Requests\CustomerFollowUpsRequest;
use App\Features\Customers\Requests\CustomerPrivacyActionRequest;
use App\Features\Customers\Requests\GrantComplimentaryMediaRequest;
use App\Features\Customers\Requests\GrantCustomerAccountCreditRequest;
use App\Features\Customers\Requests\MergeCustomerAccountsRequest;
use App\Features\Customers\Requests\RevokeComplimentaryMediaRequest;
use App\Features\Customers\Requests\SendCustomerAccountEmailRequest;
use App\Features\Customers\Requests\StoreCustomerAccountNoteRequest;
use App\Features\Customers\Requests\StoreCustomerRiskEventRequest;
use App\Features\Customers\Requests\UpdateAdminCustomerContactRequest;
use App\Features\Customers\Requests\UpdateCustomerAccountStatusRequest;
use App\Features\Customers\Requests\UpdateCustomerPreferencesRequest;
use App\Features\Customers\Services\ComplimentaryMediaGrants;
use App\Features\Customers\Services\CustomerAccountAdministration;
use App\Features\Customers\Services\CustomerAccountIntelligence;
use App\Features\Customers\Services\CustomerAccountMediaPreview;
use App\Features\Customers\Services\CustomerAccountSearch;
use App\Features\Customers\Services\CustomerAccountWorkspace;
use App\Features\Customers\Services\CustomerFollowUps;
use App\Features\Customers\Services\MergeCustomerAccounts;
use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\Order;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCustomerAccountController extends Controller
{
    public function mediaAsset(User $customer, MediaAsset $asset, CustomerAccountMediaPreview $preview): StreamedResponse
    {
        return $preview->response($customer, $asset);
    }

    public function search(Request $request, CustomerAccountSearch $customers): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        abort_if(mb_strlen($term) > 255, 422);

        return response()->json(['data' => $customers->search($term)]);
    }

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $orderId = Order::idFromDisplayNumber($term);
        $customers = User::query()->where('type', 'customer')->when($term !== '', function ($query) use ($term, $orderId): void {
            $query->where(function ($query) use ($term, $orderId): void {
                $query->where('email', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $query->orWhere('id', (int) $term);
                }
                if ($orderId !== null) {
                    $query->orWhereHas('orders', fn ($query) => $query->whereKey($orderId));
                }
            });
        })->withCount(['orders as paid_orders_count' => fn ($query) => $query->whereIn('status', ['paid', 'fulfilled', 'refunded'])->where(fn ($q) => $q->whereNull('metadata->source')->orWhere('metadata->source', '!=', 'complimentary_media_grant'))])
            ->withSum(['orders as lifetime_value' => fn ($query) => $query->whereIn('status', ['paid', 'fulfilled', 'refunded'])->where(fn ($q) => $q->whereNull('metadata->source')->orWhere('metadata->source', '!=', 'complimentary_media_grant'))], 'total_amount')
            ->orderBy('name')->paginate(50)->withQueryString();

        return response()->view('admin.customers.index', compact('customers', 'term'))->header('Cache-Control', 'private, no-store');
    }

    public function followUps(CustomerFollowUpsRequest $request, CustomerFollowUps $followUps): Response
    {
        return response()->view('admin.customers.follow-ups', $followUps->build($request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function show(AdminCustomerPageRequest $request, User $customer, CustomerAccountWorkspace $workspace): Response
    {
        abort_unless($customer->type === 'customer', 404);

        return response()->view('admin.customers.show', $workspace->build($customer, $request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function grant(GrantComplimentaryMediaRequest $request, ComplimentaryMediaGrants $grants): RedirectResponse
    {
        $order = $grants->grant($request->user(), $request->validated());
        $target = $request->validated('return_to');
        if (! $target || ! str_starts_with($target, '/admin/')) {
            $target = route('admin.customers.show', $order->user_id);
        }

        return redirect($target)->with('status', 'Media added to account #'.$order->user_id.' at no cost.');
    }

    public function revokeComplimentaryMedia(RevokeComplimentaryMediaRequest $request, User $customer, Order $order, ComplimentaryMediaGrants $grants): RedirectResponse
    {
        abort_unless($order->user_id === $customer->id, 404);
        $grants->revoke($order, $request->user(), $request->validated('reason'), $request->boolean('notify_customer'));

        return back()->with('status', 'Complimentary media access revoked.');
    }

    public function updateStatus(UpdateCustomerAccountStatusRequest $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        $accounts->status($customer, $request->user(), $request->validated('status'), $request->validated('reason'));

        return back()->with('status', 'Customer account status updated.');
    }

    public function updateContact(UpdateAdminCustomerContactRequest $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        $emailChanged = $customer->email !== $request->validated('email');
        $accounts->contact($customer, $request->user(), $request->validated());
        if ($emailChanged) {
            $customer->sendEmailVerificationNotification();
        }

        return back()->with('status', $emailChanged
            ? 'Customer contact details updated and a verification link sent to the new email address.'
            : 'Customer contact details updated.');
    }

    public function sendPasswordReset(Request $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        abort_unless($customer->type === 'customer', 404);
        $status = Password::sendResetLink(['email' => $customer->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['password_reset' => __($status)]);
        }
        $accounts->passwordResetRequested($customer, $request->user());

        return back()->with('status', 'Password reset link sent to '.$customer->email.'.');
    }

    public function sendEmail(SendCustomerAccountEmailRequest $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        $accounts->email($customer, $request->user(), $request->validated('subject'), $request->validated('body'));

        return back()->with('status', 'Email sent and recorded in the customer history.');
    }

    public function addEvent(AddCustomerEventAccessRequest $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        $accounts->event($customer, $request->user(), $request->validated('event_kind'), $request->validated('event_uuid'), $request->validated('reason'));

        return back()->with('status', 'Event access added to the customer account.');
    }

    public function addCredit(GrantCustomerAccountCreditRequest $request, User $customer, CustomerAccountAdministration $accounts): RedirectResponse
    {
        $accounts->credit($customer, $request->user(), (int) round((float) $request->validated('amount') * 100), $request->validated('credit_type'), $request->validated('note'));

        return back()->with('status', 'Account credit added.');
    }

    public function refund(CreateCustomerRefundRequest $request, User $customer, Order $order, CustomerAccountAdministration $accounts): RedirectResponse
    {
        abort_unless($order->user_id === $customer->id, 404);
        $refund = $accounts->refund($order, $request->user(), $request->validated('order_item_ids'), $request->validated('reason'), $request->boolean('revoke_access'));

        return back()->with('status', 'Refund '.$refund->displayNumber().' prepared for the payment provider. No money has moved yet.');
    }

    public function preferences(UpdateCustomerPreferencesRequest $request, User $customer, CustomerAccountIntelligence $intelligence): RedirectResponse
    {
        $intelligence->preferences($customer, $request->user(), $request->validated());

        return back()->with('status', 'Contact preferences updated and recorded.');
    }

    public function note(StoreCustomerAccountNoteRequest $request, User $customer, CustomerAccountIntelligence $intelligence): RedirectResponse
    {
        $intelligence->note($customer, $request->user(), $request->validated());

        return back()->with('status', 'Internal note added.');
    }

    public function completeNote(Request $request, User $customer, CustomerAccountNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $customer->id, 404);
        $note->update(['completed_at' => now(), 'completed_by_user_id' => $request->user()->id]);

        return back()->with('status', 'Follow-up completed.');
    }

    public function risk(StoreCustomerRiskEventRequest $request, User $customer, CustomerAccountIntelligence $intelligence): RedirectResponse
    {
        $intelligence->risk($customer, $request->user(), $request->validated());

        return back()->with('status', 'Risk event recorded.');
    }

    public function resolveRisk(Request $request, User $customer, CustomerRiskEvent $riskEvent): RedirectResponse
    {
        abort_unless($riskEvent->user_id === $customer->id, 404);
        $riskEvent->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('status', 'Risk event resolved.');
    }

    public function merge(MergeCustomerAccountsRequest $request, MergeCustomerAccounts $merger): RedirectResponse
    {
        $source = User::where('type', 'customer')->findOrFail($request->validated('source_customer_id'));
        $target = User::where('type', 'customer')->findOrFail($request->validated('target_customer_id'));
        $merger->execute($source, $target, $request->user(), $request->validated('confirmation_email'), $request->validated('reason'));

        return to_route('admin.customers.show', $target)->with('status', 'Accounts merged into this primary account.');
    }

    public function privacy(CustomerPrivacyActionRequest $request, User $customer, CustomerAccountIntelligence $intelligence): RedirectResponse|StreamedResponse
    {
        if ($request->validated('action') === 'export') {
            $data = $intelligence->export($customer, $request->user(), $request->validated('reason'));

            return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)), 'dancepro-customer-'.$customer->id.'-export.json', ['Content-Type' => 'application/json']);
        } $intelligence->anonymize($customer, $request->user(), $request->validated('confirmation_email'), $request->validated('reason'));

        return to_route('admin.customers.show', $customer)->with('status', 'Customer personal data anonymised; financial audit records were retained.');
    }
}
