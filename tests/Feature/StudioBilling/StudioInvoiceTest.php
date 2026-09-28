<?php

namespace Tests\Feature\StudioBilling;

use App\Features\Bookings\Actions\ApproveConcertBooking;
use App\Features\Bookings\Actions\CreateConcertBooking;
use App\Features\Bookings\Models\ConcertBooking;
use App\Features\Scheduling\Models\EventTypeDefinition;
use App\Features\StudioBilling\Models\StudioBillingSetting;
use App\Features\StudioBilling\Models\StudioInvoice;
use App\Features\StudioBilling\Models\StudioInvoicePayment;
use App\Features\StudioBilling\Services\StudioInvoiceGateway;
use App\Features\StudioBilling\Services\StudioInvoices;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Jobs\SendConcertTrackerEmail;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Services\ConcertTracker;
use App\Features\Venues\Models\Venue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_issue_hub_payment_and_pdf_journey(): void
    {
        [$booking,$studio,$staff,$customer] = $this->fixture();
        $this->actingAs($staff)->post(route('admin.studio-invoices.store', $booking), ['kind' => 'video', 'is_test' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $invoice = StudioInvoice::sole();
        $this->assertSame(300000, $invoice->total_cents);
        $this->assertSame('100001', $invoice->number());
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))->assertOk()->assertSee('Send invoice');
        $this->get(route('admin.studio-invoices.index'))->assertOk();
        $this->get(route('admin.studio-invoices.settings'))->assertOk();
        $this->actingAs($customer)->get(route('customer.studio-invoices.show', [$studio, $invoice]))->assertNotFound();
        $this->actingAs($staff)->post(route('admin.studio-invoices.issue', $invoice))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('customer.studio-invoices.index', $studio))->assertOk()->assertSee('100001');
        $this->get(route('customer.studio-invoices.show', [$studio, $invoice]))->assertOk()->assertSee('Simulate payment');
        $pdf = $this->get(route('customer.studio-invoices.pdf', [$studio, $invoice]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $data = ['idempotency_key' => (string) Str::uuid(), 'balance_cents' => 300000];
        $this->post(route('customer.studio-invoices.pay', [$studio, $invoice]), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('customer.studio-invoices.pay', [$studio, $invoice]), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame(0, $invoice->fresh()->balanceCents());
        $this->get(route('customer.studio-invoices.show', [$studio, $invoice]))->assertOk()->assertSee('Paid')->assertDontSee('id="invoice-payment"', false);
    }

    public function test_other_studios_customers_and_revoked_directors_cannot_access_invoice_or_pay(): void
    {
        [$booking,$studio,$staff,$customer] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $outsider = User::factory()->customer()->create();
        foreach (['show', 'pdf', 'token'] as $action) {
            $this->actingAs($outsider)->get(route('customer.studio-invoices.'.$action, [$studio, $invoice]))->assertForbidden();
        }
        $this->post(route('customer.studio-invoices.pay', [$studio, $invoice]), ['idempotency_key' => (string) Str::uuid(), 'balance_cents' => 300000])->assertForbidden();
        $this->get(route('admin.studio-invoices.index'))->assertForbidden();
        $other = Studio::factory()->create();
        StudioAccountAccess::create(['studio_id' => $other->id, 'user_id' => $customer->id, 'email' => $customer->email, 'status' => 'active', 'role' => 'director']);
        $this->actingAs($customer)->get(route('customer.studio-invoices.pdf', [$other, $invoice]))->assertNotFound();
        StudioAccountAccess::where('studio_id', $studio->id)->update(['status' => 'revoked']);
        $this->get(route('customer.studio-invoices.show', [$studio, $invoice]))->assertForbidden();
    }

    public function test_quote_changes_require_refresh_and_issued_amounts_cannot_be_edited(): void
    {
        [$booking,$studio,$staff] = $this->fixture();
        $invoice = app(StudioInvoices::class)->create($booking, $staff, true, 'video');
        $booking->update(['final_family_count' => 250]);
        $this->actingAs($staff)->post(route('admin.studio-invoices.issue', $invoice))->assertSessionHasErrors('invoice');
        $this->post(route('admin.studio-invoices.refresh', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(375000, $invoice->fresh()->total_cents);
        $this->post(route('admin.studio-invoices.issue', $invoice))->assertSessionHasNoErrors();
        $this->patch(route('admin.studio-invoices.update', $invoice), ['lines' => [['description' => 'Tamper', 'quantity' => 1, 'unit_amount' => '1']], 'due_date' => '2026-11-01'])->assertSessionHasErrors('invoice');
        $booking->update(['final_family_count' => 500]);
        $this->assertSame(375000, $invoice->fresh()->total_cents);
    }

    public function test_partial_manual_payments_and_reversals_are_audited_and_do_not_refund_money(): void
    {
        [$booking,$studio,$staff] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $data = ['idempotency_key' => (string) Str::uuid(), 'amount' => '1000.00', 'reference' => 'Bank transfer', 'paid_at' => today()->toDateString()];
        $this->actingAs($staff)->post(route('admin.studio-invoices.record-payment', $invoice), $data)->assertSessionHasNoErrors();
        $this->post(route('admin.studio-invoices.record-payment', $invoice), $data)->assertSessionHasNoErrors();
        $this->assertSame(200000, $invoice->fresh()->balanceCents());
        $this->post(route('admin.studio-invoices.record-payment', $invoice), array_merge($data, ['idempotency_key' => (string) Str::uuid(), 'amount' => '2000.01']))->assertSessionHasErrors('invoice');
        $this->post(route('admin.studio-invoices.void', $invoice), ['reason' => 'Wrong booking'])->assertSessionHasErrors('invoice');
        $payment = $invoice->payments()->sole();
        $this->post(route('admin.studio-invoices.reverse', [$invoice, $payment]), ['reason' => 'Transfer allocated to wrong invoice'])->assertSessionHasNoErrors();
        $this->assertSame(300000, $invoice->fresh()->balanceCents());
        $this->assertDatabaseHas('studio_invoice_events', ['studio_invoice_id' => $invoice->id, 'action' => 'manual_payment_reversed']);
    }

    public function test_unknown_gateway_result_blocks_duplicate_charges_and_reconciliation_clears_balance_only_when_settled(): void
    {
        [$booking,$studio,$staff,$customer] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $gateway = \Mockery::mock(StudioInvoiceGateway::class);
        $gateway->shouldReceive('available')->once()->andReturn(true);
        $gateway->shouldReceive('mode')->andReturn('braintree');
        $gateway->shouldReceive('charge')->once()->andThrow(new \RuntimeException('timeout'));
        $gateway->shouldReceive('reconcile')->once()->andReturn(['status' => 'submitted', 'transaction' => 'tx1', 'message' => 'Pending']);
        $this->app->instance(StudioInvoiceGateway::class, $gateway);
        $data = ['idempotency_key' => (string) Str::uuid(), 'balance_cents' => 300000, 'nonce' => 'test-nonce'];
        $this->actingAs($customer)->post(route('customer.studio-invoices.pay', [$studio, $invoice]), $data)->assertSessionHasNoErrors();
        $this->assertSame('unknown', $invoice->payments()->sole()->status);
        $this->post(route('customer.studio-invoices.pay', [$studio, $invoice]), array_merge($data, ['idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('invoice');
        $this->post(route('customer.studio-invoices.reconcile', [$studio, $invoice]))->assertSessionHasNoErrors();
        $this->assertSame(300000, $invoice->fresh()->balanceCents());
        $gateway2 = \Mockery::mock(StudioInvoiceGateway::class);
        $gateway2->shouldReceive('reconcile')->once()->andReturn(['status' => 'succeeded', 'transaction' => 'tx1', 'message' => 'Settled']);
        $this->app->instance(StudioInvoiceGateway::class, $gateway2);
        $this->post(route('customer.studio-invoices.reconcile', [$studio, $invoice]))->assertSessionHasNoErrors();
        $this->assertSame(0, $invoice->fresh()->balanceCents());
    }

    public function test_fake_gateway_never_pays_live_invoice_and_amount_is_server_validated(): void
    {
        [$booking,$studio,$staff,$customer] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $data = ['idempotency_key' => (string) Str::uuid(), 'balance_cents' => 1];
        $this->actingAs($customer)->post(route('customer.studio-invoices.pay', [$studio, $invoice]), $data)->assertSessionHasErrors('invoice');
        $invoice->update(['is_test' => false]);
        $data['balance_cents'] = 300000;
        $this->post(route('customer.studio-invoices.pay', [$studio, $invoice]), $data)->assertSessionHasErrors('invoice');
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_tracker_invoice_links_describe_creation_draft_and_issued_destinations(): void
    {
        [$booking, , $staff] = $this->fixture();
        $event = $booking->items()->where('item_type', 'concert')->first()->schedulingEvent;
        $this->actingAs($staff);

        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'invoice_sent']))
            ->assertRedirect(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']));
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))
            ->assertOk()->assertSee('Create draft invoice')->assertSee('Tracker task controls');
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'payment_received']))
            ->assertRedirect(route('admin.concert-tracker.tasks.show', [$event, 'invoice_sent']));
        $this->assertDatabaseCount('studio_invoices', 0);
        $invoice = app(StudioInvoices::class)->create($booking, $staff, true, 'video');
        $this->get(route('admin.concert-tracker.tasks.show', [$event, 'invoice_sent']))
            ->assertRedirect(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']));
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))->assertOk()->assertSee('Tracker task controls')->assertSee('Discount (AUD');
        $this->post(route('admin.studio-invoices.issue', $invoice))->assertRedirect(route('admin.studio-invoices.email', $invoice));
        $this->get(route('admin.studio-invoices.email', $invoice))->assertOk()->assertSee('VIEW AND PAY INVOICE')->assertSee('Tracker task controls');
    }

    public function test_tracker_requires_booking_review_before_offering_invoice_creation(): void
    {
        [$booking, , $staff] = $this->fixture();
        $event = $booking->items()->where('item_type', 'concert')->first()->schedulingEvent;
        $booking->update(['status' => 'pending']);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))
            ->assertOk()->assertSee('Review booking')->assertDontSee('Create draft invoice');

        $booking->update(['status' => 'approved', 'studio_id' => null]);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))
            ->assertOk()->assertSee('Review booking')->assertDontSee('Create draft invoice');
    }

    public function test_tracker_reads_invoice_and_settled_balance_not_manual_completion(): void
    {
        [$booking,$studio,$staff] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $event = $booking->items()->where('item_type', 'concert')->first()->schedulingEvent;
        $tracker = app(ConcertTracker::class);
        $task = $tracker->detail($event, 'invoice_sent');
        $this->assertSame('Not invoiced', $task['status']['label']);
        app(StudioInvoices::class)->recordPayment($invoice, $staff, ['idempotency_key' => (string) Str::uuid(), 'amount' => '3000', 'reference' => 'Received', 'paid_at' => today()->toDateString()]);
        $task = $tracker->detail($event, 'invoice_sent');
        $this->assertSame('complete', $task['status']['key']);
    }

    public function test_custom_invoice_settings_snapshot_void_and_replacement(): void
    {
        [$booking, $studio, $staff] = $this->fixture();
        $service = app(StudioInvoices::class);
        $invoice = $service->create($booking, $staff, true, 'custom');
        $this->actingAs($staff)->post(route('admin.studio-invoices.issue', $invoice))->assertSessionHasErrors('invoice');
        $this->patch(route('admin.studio-invoices.update', $invoice), ['lines' => [['description' => 'Reviewed additional services', 'quantity' => 2, 'unit_amount' => '55.00']], 'due_date' => '2026-11-10'])->assertSessionHasNoErrors();
        $this->post(route('admin.studio-invoices.issue', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(11000, $invoice->fresh()->total_cents);
        $this->assertSame(1000, $invoice->fresh()->gst_cents);
        $this->put(route('admin.studio-invoices.settings.save'), ['business_name' => 'Changed business', 'abn' => '77221718867', 'email' => 'billing@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('DancePro Photography & Video', $invoice->fresh()->issuer['business_name']);
        $this->post(route('admin.studio-invoices.void', $invoice), ['reason' => 'Replace incorrect invoice'])->assertSessionHasNoErrors();
        $replacement = $service->create($booking, $staff, true, 'video');
        $this->assertNotSame($invoice->id, $replacement->id);
        $this->assertNull($invoice->fresh()->active_booking_id);
        $this->assertSame('void', $invoice->fresh()->status);
    }

    public function test_braintree_results_require_matching_invoice_amount_currency_and_settlement(): void
    {
        $payment = new StudioInvoicePayment(['uuid' => (string) Str::uuid(), 'amount_cents' => 150000]);
        $gateway = new StudioInvoiceGateway;
        $transaction = (object) ['orderId' => $payment->uuid, 'currencyIsoCode' => 'AUD', 'amount' => '1500.00', 'id' => 'test-tx', 'status' => 'submitted_for_settlement'];
        $this->assertSame('submitted', $gateway->normalise($transaction, $payment)['status']);
        $transaction->status = 'settled';
        $this->assertSame('succeeded', $gateway->normalise($transaction, $payment)['status']);
        $transaction->status = 'settlement_declined';
        $this->assertSame('failed', $gateway->normalise($transaction, $payment)['status']);
        $transaction->currencyIsoCode = 'USD';
        $this->expectException(\RuntimeException::class);
        $gateway->normalise($transaction, $payment);
    }

    public function test_unconfirmed_family_numbers_do_not_create_video_invoice(): void
    {
        [$booking, , $staff] = $this->fixture();
        $booking->update(['family_count_confirmed_at' => null]);
        $this->actingAs($staff)->post(route('admin.studio-invoices.store', $booking), ['kind' => 'video'])->assertSessionHasErrors('invoice');
        $this->assertDatabaseCount('studio_invoices', 0);
    }

    public function test_pending_invoice_cannot_be_voided_manually_paid_or_recharged(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $invoice->payments()->create(['idempotency_key' => (string) Str::uuid(), 'user_id' => $customer->id, 'provider' => 'braintree', 'status' => 'submitted', 'is_test' => true, 'amount_cents' => 300000, 'currency' => 'AUD']);
        $this->actingAs($staff)->post(route('admin.studio-invoices.void', $invoice), ['reason' => 'Cancel'])->assertSessionHasErrors('invoice');
        $this->post(route('admin.studio-invoices.record-payment', $invoice), ['idempotency_key' => (string) Str::uuid(), 'amount' => '3000', 'reference' => 'Transfer', 'paid_at' => today()->toDateString()])->assertSessionHasErrors('invoice');
        $this->actingAs($customer)->post(route('customer.studio-invoices.pay', [$studio, $invoice]), ['idempotency_key' => (string) Str::uuid(), 'balance_cents' => 300000])->assertSessionHasErrors('invoice');
        $this->assertSame(300000, $invoice->fresh()->balanceCents());
    }

    public function test_invoice_task_tracks_numbers_sending_deadlines_discount_and_payment(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-01 10:00'));
        [$booking, $studio, $staff, $customer] = $this->fixture();
        $event = $booking->items()->first()->schedulingEvent;
        $tracker = app(ConcertTracker::class);
        $booking->update(['family_count_confirmed_at' => null]);
        $status = $tracker->detail($event->fresh(), 'invoice_sent')['status'];
        $this->assertSame('Pending', $status['label']);
        $this->assertNull($status['dueDate']);
        $booking->update(['family_count_confirmed_at' => now()]);
        $status = $tracker->detail($event->fresh(), 'invoice_sent')['status'];
        $this->assertSame('Not invoiced', $status['label']);
        $this->assertSame('2026-10-14', $status['dueDate']->toDateString());
        $invoice = app(StudioInvoices::class)->create($booking, $staff, true, 'video');
        $this->actingAs($staff)->patch(route('admin.studio-invoices.update', $invoice), [
            'lines' => [['description' => 'Two concerts', 'quantity' => 1, 'unit_amount' => '3000.00']],
            'discount_amount' => '100.00', 'notes' => 'Thank you for returning.', 'send_invoice' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.studio-invoices.email', $invoice));
        $this->assertSame(290000, $invoice->fresh()->total_cents);
        $this->assertSame(26364, $invoice->fresh()->gst_cents);
        $this->assertNull($invoice->fresh()->sent_at);
        $this->get(route('admin.studio-invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $emailUrl = route('admin.concert-tracker.tasks.email.send', [$event, 'invoice_sent']);
        $payload = ['recipient_email' => $customer->email, 'studio_invoice_uuid' => $invoice->uuid];
        $this->post($emailUrl, $payload)->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        $this->assertStringContainsString(route('customer.studio-invoices.show', [$studio, $invoice]), $delivery->body);
        $this->assertSame('Not invoiced', $tracker->detail($event->fresh(), 'invoice_sent')['status']['label']);
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertSame('2026-10-15', $invoice->fresh()->due_date->toDateString());
        $status = $tracker->detail($event->fresh(), 'invoice_sent')['status'];
        $this->assertSame('Not paid', $status['label']);
        $this->assertSame('near-due', $status['tone']);
        $this->assertStringContainsString($staff->name, $status['activity']);
        $this->post($emailUrl, $payload)->assertSessionHasErrors('email');
        $this->travel(15)->days();
        $this->assertSame('overdue', $tracker->detail($event->fresh(), 'invoice_sent')['status']['tone']);
        $this->post($emailUrl, $payload + ['confirm_resend' => true, 'previous_delivery_id' => $delivery->id])->assertSessionHasNoErrors();
        (new SendConcertTrackerEmail(ConcertTrackerEmailDelivery::latest('id')->value('id')))->handle();
        $this->assertSame('2026-10-15', $invoice->fresh()->due_date->toDateString());
        app(StudioInvoices::class)->recordPayment($invoice, $staff, ['idempotency_key' => (string) Str::uuid(), 'amount' => '2900', 'reference' => 'Transfer', 'paid_at' => today()->toDateString()]);
        $status = $tracker->detail($event->fresh(), 'invoice_sent')['status'];
        $this->assertSame('Paid', $status['label']);
        $this->assertSame('complete', $status['tone']);
        $this->assertStringStartsWith('Paid 16 Oct 2026', $status['activity']);
        $this->assertStringNotContainsString('Sent', $status['activity']);
        $this->assertNull($status['dueDate']);
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))->assertOk()->assertSee('Transfer')->assertSee('Download PDF');
        $this->actingAs($customer)->get(route('admin.studio-invoices.email', $invoice))->assertForbidden();
    }

    public function test_voiding_an_invoice_cancels_its_queued_email(): void
    {
        Mail::fake();
        [$booking, , $staff, $customer] = $this->fixture();
        $invoice = $this->issued($booking, $staff);
        $event = $booking->items()->first()->schedulingEvent;
        $this->actingAs($staff)->post(route('admin.concert-tracker.tasks.email.send', [$event, 'invoice_sent']),
            ['recipient_email' => $customer->email, 'studio_invoice_uuid' => $invoice->uuid])->assertSessionHasNoErrors();
        $delivery = ConcertTrackerEmailDelivery::latest('id')->firstOrFail();
        app(StudioInvoices::class)->void($invoice, $staff, 'Replaced');
        (new SendConcertTrackerEmail($delivery->id))->handle();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertNull($invoice->fresh()->sent_at);
        Mail::assertNothingSent();
    }

    public function test_draft_explains_the_same_business_detail_blocker_as_issuing(): void
    {
        [$booking, , $staff] = $this->fixture();
        $service = app(StudioInvoices::class);
        $invoice = $service->create($booking, $staff, true, 'video');
        StudioBillingSetting::query()->update(['abn' => null]);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']))->assertOk()
            ->assertSee('Resolve these requirements before sending the invoice')
            ->assertSee('Set all invoice business details before issuing.')
            ->assertSee('Task navigation')->assertSee('Back to event');
        $this->assertContains('Set all invoice business details before issuing.', $service->issueBlockers($invoice));
    }

    public function test_invoice_tab_is_the_single_draft_workspace_and_preserves_tracker_context(): void
    {
        [$booking, , $staff] = $this->fixture();
        $url = route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']);
        $this->actingAs($staff)->get(route('admin.concert-bookings.show', $booking))
            ->assertOk()->assertSee('4.2 Invoice &amp; payment', false)->assertDontSee('Create draft invoice');
        $this->post(route('admin.studio-invoices.store', $booking), ['kind' => 'video', 'is_test' => true])
            ->assertSessionHasNoErrors()->assertRedirect($url);
        $invoice = $booking->fresh()->activeStudioInvoice;
        $context = ['view' => 'studio', 'year' => 2026, 'phase' => 'completion'];
        $this->withSession(['concert_tracker_context' => $context])->get($url)
            ->assertOk()->assertSeeInOrder(['Booking tasks', '4.2 Invoice &amp; payment', 'Tracker task controls', 'Edit draft'], false)
            ->assertSee(route('admin.concert-tracker.index', $context))
            ->assertDontSee('action="'.route('admin.concert-bookings.update', $booking).'"', false);
        $this->get(route('admin.studio-invoices.show', $invoice))
            ->assertRedirect(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice', 'invoice' => $invoice->uuid]));
        $this->from($url)->patch(route('admin.studio-invoices.update', $invoice), [
            'lines' => [['description' => 'Concert video', 'quantity' => 1, 'unit_amount' => '3000.00']],
            'discount_amount' => '50.00', 'notes' => 'Reviewed in booking',
        ])->assertSessionHasNoErrors()->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Reviewed in booking')->assertSee('50.00');
        $this->assertDatabaseCount('studio_invoices', 1);
    }

    public function test_invoice_tab_restricts_invoice_selection_to_the_booking_and_keeps_void_history(): void
    {
        [$booking, , $staff, $customer] = $this->fixture();
        $service = app(StudioInvoices::class);
        $invoice = $service->create($booking, $staff, true, 'video');
        $service->void($invoice, $staff, 'Replace draft');
        $url = route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice']);
        $this->actingAs($staff)->get($url)->assertOk()->assertSee('Create draft invoice')->assertSee('Other invoices for this booking');
        $this->get(route('admin.concert-bookings.show', [$booking, 'tab' => 'invoice', 'invoice' => $invoice->uuid]))
            ->assertOk()->assertSee('Replace draft')->assertSee('Create replacement invoice')->assertDontSee('Edit draft');
        $otherBooking = $booking->replicate();
        $otherBooking->uuid = (string) Str::uuid();
        $otherBooking->save();
        $this->get(route('admin.concert-bookings.show', [$otherBooking, 'tab' => 'invoice', 'invoice' => $invoice->uuid]))->assertNotFound();
        $this->actingAs($customer)->get($url)->assertForbidden();
    }

    private function issued(ConcertBooking $booking, User $staff): StudioInvoice
    {
        $service = app(StudioInvoices::class);
        $invoice = $service->create($booking, $staff, true, 'video');
        $service->issue($invoice, $staff);

        return $invoice->fresh();
    }

    public function test_invoice_pages_show_linked_hub_summary_without_marking_messages_read(): void
    {
        [$booking, $studio, $staff, $customer] = $this->fixture();
        $message = StudioConversationMessage::create([
            'studio_id' => $studio->id, 'direction' => 'from_dancepro', 'channel' => 'web',
            'subject' => 'Booking update', 'body' => 'Please check your event details.', 'sent_at' => now(),
        ]);
        $this->actingAs($staff)->post(route('admin.studio-invoices.store', $booking), ['kind' => 'video', 'is_test' => 1]);
        $invoice = StudioInvoice::sole();
        $this->post(route('admin.studio-invoices.issue', $invoice))->assertSessionHasNoErrors();

        $eventsUrl = route('customer.studio-communications.index', [$studio, 'tab' => 'events']);
        $actionsUrl = route('customer.studio-communications.index', [$studio, 'tab' => 'events', 'actions' => 1]);
        $messagesUrl = route('customer.studio-communications.index', [$studio, 'tab' => 'messages']);
        foreach ([route('customer.studio-invoices.index', $studio), route('customer.studio-invoices.show', [$studio, $invoice])] as $url) {
            $response = $this->actingAs($customer)->get($url)->assertOk()
                ->assertSee('Studio hub summary')->assertSee('actions remaining');
            $doc = new \DOMDocument;
            @$doc->loadHTML($response->getContent());
            $links = (new \DOMXPath($doc))->query('//a[@class="hub-stat"]');
            $this->assertCount(3, $links);
            $this->assertSame([$eventsUrl.'#events', $actionsUrl.'#events', $messagesUrl.'#messages'], array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($links)));
            $this->assertNull($message->fresh()->studio_read_at);
        }

        $actions = $this->get($actionsUrl)->assertOk();
        $doc = new \DOMDocument;
        @$doc->loadHTML($actions->getContent());
        $this->assertGreaterThan(0, (new \DOMXPath($doc))->query('//details[contains(@class,"hub-card")][@open]')->length);
        $this->get($messagesUrl)->assertOk()->assertSee('Booking update');
    }

    private function fixture(): array
    {
        Queue::fake();
        config(['studio-billing.driver' => 'fake']);
        $studio = Studio::factory()->create();
        $staff = User::factory()->staff()->create();
        $customer = User::factory()->customer()->create();
        StudioAccountAccess::create(['studio_id' => $studio->id, 'user_id' => $customer->id, 'email' => $customer->email, 'status' => 'active', 'role' => 'director']);
        StudioBillingSetting::create(['business_name' => 'DancePro Photography & Video', 'abn' => '77221718867', 'email' => 'info@dancepro.com.au', 'bank_name' => 'DancePro Photography & Video', 'bank_bsb' => '066202', 'bank_account' => '10387626']);
        $venue = Venue::create(['name' => 'Test theatre', 'address_line_1' => '1 Example Road']);
        $type = EventTypeDefinition::firstOrCreate(['name' => 'Test concert'], ['code' => 'test-concert', 'system_category' => 'concert', 'is_active' => true]);
        $data = ['studio_name' => $studio->name, 'contact_name' => 'Test Director', 'contact_email' => $customer->email, 'contact_phone' => '0400000000', 'wants_portrait_photography' => false, 'wants_concert_photography' => true, 'wants_concert_videography' => true, 'approximate_family_count' => 80, 'accepted_requirements' => ['accurate'], 'items' => []];
        foreach ([20, 21] as $day) {
            $data['items'][] = ['item_type' => 'concert', 'event_type_definition_id' => $type->id, 'title' => 'Concert '.$day, 'venue_uuid' => $venue->uuid, 'event_date' => '2026-10-'.$day, 'starts_at' => '18:00', 'finishes_at' => '20:00'];
        }
        $booking = app(CreateConcertBooking::class)->execute($data, $studio);
        app(ApproveConcertBooking::class)->execute($booking, $staff, null);
        $booking->refresh()->update(['final_family_count' => 80, 'family_count_confirmed_at' => now()]);

        return [$booking->fresh(), $studio, $staff, $customer];
    }
}
