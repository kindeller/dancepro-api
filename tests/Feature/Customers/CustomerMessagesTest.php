<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Actions\BackfillCustomerInbox;
use App\Features\Customers\Actions\ResolveCustomerSupportRequest;
use App\Features\Customers\Models\CustomerAccountEmail;
use App\Features\Customers\Models\CustomerCompetitionRegistration;
use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerConversationReply;
use App\Features\Customers\Services\CustomerInbox;
use App\Features\Customers\Services\CustomerPrivacyData;
use App\Features\Customers\Services\MergeCustomerAccounts;
use App\Features\Orders\Models\Order;
use App\Features\Payments\Models\CustomerMediaNotification;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_admin_can_reply_in_one_private_thread_with_unread_counts(): void
    {
        Notification::fake();
        Storage::fake('local');
        $customer = $this->customer();
        $admin = User::factory()->staff()->create();
        $this->actingAs($customer)->post(route('customer.messages.store'), [
            'type' => 'general', 'subject' => 'Photo question', 'message' => 'Can you help?',
            'attachment' => UploadedFile::fake()->image('example.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $thread = CustomerSupportRequest::firstOrFail();
        $this->assertCount(1, $thread->messages);
        $attachment = $thread->messages->first();
        Storage::disk('local')->assertExists($attachment->attachment['path']);
        $this->get(route('customer.messages.attachment', $attachment))->assertOk()->assertDownload('example.jpg');
        $this->assertSame(0, app(CustomerInbox::class)->counts($customer)['total']);
        $this->actingAs($admin)->get(route('admin.media-support.conversation', $thread))->assertOk()->assertSee('Can you help?');
        $this->post(route('admin.media-support.reply', $thread), ['message' => 'Of course.'])->assertRedirect()->assertSessionHasNoErrors();
        Notification::assertSentTo($customer, CustomerConversationReply::class);
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['conversations']);
        $this->actingAs($customer)->get(route('customer.messages.index', ['tab' => 'conversations']))->assertOk()->assertSee('Unread reply');
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['conversations']);
        $this->get(route('customer.messages.show', $thread))->assertOk()->assertSee('Of course.');
        $this->assertSame(0, app(CustomerInbox::class)->counts($customer)['total']);
        $this->post(route('customer.messages.reply', $thread), ['message' => 'One more question.'])->assertRedirect();
        $this->assertSame('pending', $thread->fresh()->status);
        $this->get(route('customer.messages.show', $thread))->assertOk()->assertSee('One more question.');
        $this->assertSame(3, $thread->messages()->count());
        $this->assertDatabaseCount('customer_support_requests', 1);
        $other = $this->customer();
        $this->actingAs($other)->get(route('customer.messages.show', $thread))->assertNotFound();
        $this->post(route('customer.messages.reply', $thread), ['message' => 'Not mine'])->assertNotFound();
        $this->get(route('customer.messages.attachment', $attachment))->assertNotFound();
        $this->get(route('admin.media-support.conversation', $thread))->assertForbidden();
    }

    public function test_automated_notifications_are_deduplicated_and_read_state_survives_delivery_updates(): void
    {
        $customer = $this->customer();
        $source = CustomerMediaNotification::create(['user_id' => $customer->id, 'type' => 'receipt', 'deduplication_key' => 'receipt:test',
            'recipient_email' => $customer->email, 'subject' => 'Your receipt', 'status' => 'queued', 'queued_at' => now(),
            'metadata' => ['body' => 'Payment received.', 'action_url' => route('customer.media.index'), 'action_label' => 'My Media']]);
        $notification = CustomerInboxNotification::firstOrFail();
        $this->actingAs($customer)->get(route('customer.messages.index'))->assertOk()->assertSee('Payment received.')->assertSee('queued');
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['total']);
        $this->post(route('customer.messages.read', $notification))->assertRedirect();
        $source->update(['status' => 'sent', 'sent_at' => now()]);
        app(BackfillCustomerInbox::class)->execute();
        app(BackfillCustomerInbox::class)->execute();
        $this->assertDatabaseCount('customer_inbox_notifications', 1);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame('sent', $notification->fresh()->email_status);
        $this->assertSame(0, app(CustomerInbox::class)->counts($customer)['total']);
        $this->actingAs($this->customer())->post(route('customer.messages.read', $notification))->assertNotFound();
        $this->get(route('customer.messages.index'))->assertDontSee('Your receipt');
    }

    public function test_personal_emails_and_legacy_support_responses_have_one_conversation_each(): void
    {
        $customer = $this->customer();
        $email = CustomerAccountEmail::create(['user_id' => $customer->id, 'recipient_email' => $customer->email,
            'subject' => 'A personal update', 'body' => 'Hello from David', 'status' => 'queued', 'queued_at' => now()]);
        $email->update(['status' => 'sent', 'sent_at' => now()]);
        $thread = CustomerSupportRequest::firstOrFail();
        $this->assertSame(1, $thread->messages()->count());
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['conversations']);
        $legacy = CustomerSupportRequest::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'Legacy help',
            'message' => 'Original request', 'status' => 'resolved', 'requested_at' => now()->subDay(), 'decided_at' => now(), 'response_note' => 'Original response']);
        app(BackfillCustomerInbox::class)->execute();
        $this->actingAs($customer)->get(route('customer.messages.show', $legacy))->assertOk()->assertSee('Original request')->assertSee('Original response');
        $this->post(route('customer.messages.reply', $legacy), ['message' => 'Thanks'])->assertRedirect();
        $this->get(route('customer.messages.show', $legacy))->assertOk();
        $this->assertSame(3, $legacy->messages()->count());
        app(BackfillCustomerInbox::class)->execute();
        $this->assertSame(3, $legacy->messages()->count());
        $this->assertDatabaseCount('customer_support_requests', 2);
    }

    public function test_new_message_context_is_owned_and_html_is_escaped(): void
    {
        $customer = $this->customer();
        $other = $this->customer();
        $order = Order::factory()->paid()->create(['user_id' => $other->id]);
        $event = SchedulingEvent::create(['name' => 'Private event', 'event_type' => 'competition', 'event_date' => now()]);
        $payload = ['type' => 'general', 'subject' => '<script>alert(1)</script>', 'message' => '<img src=x onerror=alert(1)>'];
        $this->actingAs($customer)->post(route('customer.messages.store'), $payload + ['order_id' => $order->id])->assertSessionHasErrors('order_id');
        $this->post(route('customer.messages.store'), $payload + ['event_uuid' => $event->uuid])->assertSessionHasErrors('event_uuid');
        CustomerCompetitionRegistration::create(['user_id' => $customer->id, 'scheduling_event_id' => $event->id, 'access_verified_at' => now()]);
        $this->get(route('customer.messages.index', ['tab' => 'new']))->assertOk()->assertSee('Private event')->assertDontSee('Order '.$order->displayNumber());
        $this->post(route('customer.messages.store'), $payload + ['event_uuid' => $event->uuid])->assertRedirect()->assertSessionHasNoErrors();
        $thread = CustomerSupportRequest::firstOrFail();
        $this->get(route('customer.messages.show', $thread))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->post(route('customer.messages.reply', $thread), ['message' => 'Unsafe file', 'attachment' => UploadedFile::fake()->create('code.html', 1, 'text/html')])->assertSessionHasErrors('attachment');
    }

    public function test_tabs_share_navigation_and_listing_is_paginated(): void
    {
        $customer = $this->customer();
        for ($i = 0; $i < 23; $i++) {
            CustomerInboxNotification::create(['user_id' => $customer->id, 'source_key' => 'fixture:'.$i, 'subject' => 'Notice '.$i, 'body' => 'Update', 'occurred_at' => now()]);
        }
        $this->actingAs($customer);
        foreach (['notifications', 'conversations', 'new'] as $tab) {
            $this->get(route('customer.messages.index', ['tab' => $tab]))->assertOk()->assertSee('My Messages')->assertSee('My Messages sections')->assertSee('23 unread messages and notifications');
        }
        $this->assertCount(20, $this->get(route('customer.messages.index'))->viewData('notifications'));
        $this->assertCount(3, $this->get(route('customer.messages.index', ['page' => 2]))->viewData('notifications'));
        $this->get(route('customer.media.help'))->assertOk()->assertSee('Contact us')->assertDontSee('Send request');
        $this->get(route('customer.messages.index', ['tab' => 'invalid']))->assertSessionHasErrors('tab');
    }

    private function customer(): User
    {
        return User::factory()->customer()->create(['onboarding_completed_at' => now()]);
    }

    public function test_two_resolutions_in_the_same_second_keep_both_replies_and_reopen_unread(): void
    {
        Notification::fake();
        $this->freezeTime();
        $customer = $this->customer();
        $admin = User::factory()->admin()->create();
        $thread = CustomerSupportRequest::create(['user_id' => $customer->id, 'type' => 'general', 'subject' => 'Question',
            'message' => 'Please help', 'status' => 'pending', 'requested_at' => now()]);
        $resolve = app(ResolveCustomerSupportRequest::class);
        $resolve->execute($thread, $admin, ['decision' => 'resolved', 'response_note' => 'First answer']);
        $this->actingAs($customer)->get(route('customer.messages.show', $thread))->assertOk();
        $this->post(route('customer.messages.reply', $thread), ['message' => 'Another question'])->assertRedirect();
        $resolve->execute($thread, $admin, ['decision' => 'resolved', 'response_note' => 'Second answer']);
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['conversations']);
        app(BackfillCustomerInbox::class)->execute();
        $this->assertSame(4, $thread->messages()->count());
        $this->get(route('customer.messages.show', $thread))->assertOk()->assertSee('First answer')->assertSee('Second answer');
    }

    public function test_messages_follow_account_merge_and_private_data_is_not_restored_by_backfill(): void
    {
        Storage::fake('local');
        $source = $this->customer();
        $target = $this->customer();
        $admin = User::factory()->admin()->create();
        $this->actingAs($source)->post(route('customer.messages.store'), ['type' => 'general', 'subject' => 'Private enquiry',
            'message' => 'Private body', 'attachment' => UploadedFile::fake()->image('private.jpg')])->assertRedirect();
        $thread = CustomerSupportRequest::firstOrFail();
        $message = $thread->messages()->firstOrFail();
        $path = $message->attachment['path'];
        CustomerAccountEmail::create(['user_id' => $source->id, 'recipient_email' => $source->email,
            'subject' => 'Private notice', 'body' => 'Secret details', 'metadata' => ['kind' => 'complimentary'], 'status' => 'sent', 'queued_at' => now()]);
        app(MergeCustomerAccounts::class)->execute($source, $target, $admin, $target->email, 'Duplicate account');
        $this->actingAs($target)->get(route('customer.messages.show', $thread))->assertOk()->assertSee('Private body');
        $export = app(CustomerPrivacyData::class)->export($target);
        $this->assertSame('Private body', $export['customer_support_messages'][0]['body']);
        $this->assertStringNotContainsString($path, json_encode($export['customer_support_messages']));
        $target->update(['anonymized_at' => now()]);
        app(CustomerPrivacyData::class)->redact($target, 'anonymous@deleted.invalid');
        app(BackfillCustomerInbox::class)->execute();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('[redacted]', $message->fresh()->body);
        $this->assertNull($message->fresh()->attachment);
        $this->assertSame('[redacted]', CustomerInboxNotification::sole()->body);
    }
}
