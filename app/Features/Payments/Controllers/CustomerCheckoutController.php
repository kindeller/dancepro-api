<?php

namespace App\Features\Payments\Controllers;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\PrepareCustomerOrder;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Payments\Requests\PrepareCustomerCheckoutRequest;
use App\Features\Payments\Requests\ProcessTestPaymentRequest;
use App\Features\Payments\Services\ProcessTestPayment;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CustomerCheckoutController extends Controller
{
    public function competition(PrepareCustomerCheckoutRequest $request, SchedulingEvent $schedulingEvent, PrepareCustomerOrder $prepare): RedirectResponse
    {
        return redirect()->route('customer.checkout.show', $prepare->competition(
            $request->user(),
            $schedulingEvent,
            $request->validated('discount_code'),
            $request->boolean('use_account_credit'),
        ));
    }

    public function concert(PrepareCustomerCheckoutRequest $request, Studio $studio, PrepareCustomerOrder $prepare): RedirectResponse
    {
        return redirect()->route('customer.checkout.show', $prepare->concertPhotos(
            $request->user(),
            $studio,
            $request->validated('discount_code'),
            $request->boolean('use_account_credit'),
        ));
    }

    public function accountCart(PrepareCustomerCheckoutRequest $request, PrepareCustomerOrder $prepare): RedirectResponse
    {
        return redirect()->route('customer.checkout.show', $prepare->accountCart(
            $request->user(),
            $request->validated('cart_groups'),
            $request->validated('discount_code'),
            $request->boolean('use_account_credit'),
        ));
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless(in_array($order->status, [OrderStatus::Draft, OrderStatus::Paid, OrderStatus::Fulfilled], true), 404);

        return response()->view('customers.checkout.show', [
            'order' => $order->load('items.mediaEntitlements.assets'),
            'idempotencyKey' => (string) Str::uuid(),
            'testMode' => app()->environment(['local', 'testing']) && config('payments.driver') === 'fake',
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pay(ProcessTestPaymentRequest $request, Order $order, ProcessTestPayment $process): RedirectResponse
    {
        $attempt = $process->execute($request->user(), $order, $request->validated('idempotency_key'), $request->validated('outcome'));

        if ($attempt->status === 'declined') {
            return back()->withErrors(['payment' => $attempt->failure_message]);
        }

        $request->session()->forget('checkout.discount_code');

        return redirect()->route('customer.checkout.show', $order)->with('status', 'Test payment approved. Your media is ready below.');
    }
}
