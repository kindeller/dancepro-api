<?php

namespace Tests\Feature\Admin;

use App\Features\Chat\Actions\PostDirectChatMessage;
use App\Features\Chat\Actions\StartDirectChat;
use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerAccountMessage;
use App\Features\Customers\Services\CustomerInbox;
use App\Features\Orders\Models\Order;
use App\Features\Studios\Models\Studio;
use App\Features\Tracking\Mail\StudioConversationReply;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageCentreTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_search_compose_reply_formatting_and_private_attachment(): void
    {
        Notification::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create(['onboarding_completed_at' => now()]);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        foreach ([$customer->name, $customer->email, $customer->uuid, $order->displayNumber(), $order->uuid] as $term) {
            $this->actingAs($admin)->get(route('admin.messages.index', ['mode' => 'new', 'q' => $term]))->assertOk()->assertSee($customer->name);
        }
        $this->get(route('admin.messages.index', ['mode' => 'new', 'recipient' => $customer->uuid]))->assertOk()->assertSee('Message formatting');
        $this->post(route('admin.messages.send'), ['channel' => 'customer', 'recipient' => $customer->uuid, 'subject' => 'Programme question', 'body' => '**Important** <script>alert(1)</script>', 'attachment' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])->assertSessionHasNoErrors()->assertRedirect();
        $thread = CustomerSupportRequest::sole();
        Notification::assertSentTo($customer, CustomerAccountMessage::class);
        $this->assertSame(1, app(CustomerInbox::class)->counts($customer)['conversations']);
        $this->get(route('admin.messages.index', ['thread' => $thread->uuid]))->assertOk()->assertSee('<strong>Important</strong>', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('notes.txt');
        $this->actingAs($customer)->get(route('customer.messages.show', $thread))->assertOk()->assertSee('<strong>Important</strong>', false);
        $this->post(route('customer.messages.reply', $thread), ['message' => 'Thanks!'])->assertRedirect();
        $this->assertSame('pending', $thread->fresh()->status);
        $this->actingAs($admin)->post(route('admin.messages.send'), ['channel' => 'customer', 'thread' => $thread->uuid, 'body' => 'You are welcome.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('resolved', $thread->fresh()->status);
        $this->assertSame(3, $thread->messages()->count());
        $attachment = $thread->messages()->first();
        $this->get(route('admin.media-support.attachment', $attachment))->assertDownload('notes.txt');
        $this->actingAs(User::factory()->customer()->create(['onboarding_completed_at' => now()]))->get(route('customer.messages.attachment', $attachment))->assertNotFound();
    }

    public function test_studio_messages_keep_the_thread_owner_history_and_contact_restrictions(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $studio = Studio::factory()->create(['contact_email' => 'director@example.test']);
        StudioConversationMessage::create(['studio_id' => $studio->id, 'direction' => 'to_dancepro', 'subject' => 'Studio question', 'body' => 'Please help.', 'sent_at' => now()]);
        $this->actingAs($admin)->get(route('admin.messages.index', ['channel' => 'studio']))->assertOk()->assertSee('1 unread');
        $this->get(route('admin.messages.index', ['channel' => 'studio', 'thread' => $studio->uuid]))->assertOk()->assertSee('Please help.')->assertSee('Save owner');
        $this->assertNotNull(StudioConversationMessage::first()->staff_read_at);
        $this->post(route('admin.messages.send'), ['channel' => 'studio', 'thread' => $studio->uuid, 'body' => '**Confirmed**', 'recipient_email' => 'outsider@example.test'])->assertSessionHasErrors('recipient_email');
        Mail::assertNothingSent();
        $this->post(route('admin.messages.send'), ['channel' => 'studio', 'thread' => $studio->uuid, 'body' => '**Confirmed**', 'subject' => 'Re: Studio question', 'attachment' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])->assertSessionHasNoErrors()->assertRedirect();
        Mail::assertSent(StudioConversationReply::class, fn ($mail) => $mail->hasTo('director@example.test'));
        $this->get(route('admin.messages.index', ['channel' => 'studio', 'thread' => $studio->uuid]))->assertOk()->assertSee('<strong>Confirmed</strong>', false)->assertSee('notes.txt');
    }

    public function test_crew_email_is_separate_from_chat_and_customer_messages(): void
    {
        Notification::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        CrewProfile::factory()->for($admin)->create();
        $crew = User::factory()->crew()->create(['onboarding_completed_at' => now()]);
        CrewProfile::factory()->for($crew)->create();
        $chat = app(StartDirectChat::class)->execute($admin, $crew);
        app(PostDirectChatMessage::class)->execute($chat, $crew, 'Private chat stays in My Hub');
        $this->actingAs($admin)->get(route('admin.messages.index', ['channel' => 'crew']))->assertOk()->assertSee('Open Crew Hub chat')->assertDontSee('Private chat stays in My Hub');
        $this->post(route('admin.messages.send'), ['channel' => 'crew', 'recipient' => $crew->uuid, 'subject' => 'Crew email subject', 'body' => 'Please review the briefing.', 'attachment' => UploadedFile::fake()->create('brief.txt', 1, 'text/plain')])->assertSessionHasNoErrors()->assertRedirect();
        Notification::assertSentTo($crew, CustomerAccountMessage::class);
        $thread = CustomerSupportRequest::sole();
        $this->assertSame('crew', $thread->context['message_audience']);
        $this->assertSame(1, $chat->messages()->count());
        $this->get(route('admin.messages.index', ['channel' => 'crew', 'thread' => $thread->uuid]))->assertOk()->assertSee('Please review the briefing.')->assertSee('brief.txt');
        $this->get(route('admin.messages.index', ['channel' => 'customer']))->assertOk()->assertDontSee('Crew email subject');
        $this->get(route('admin.messages.index'))->assertOk()->assertSee('Crew email subject')->assertViewHas('channel', 'all');
        $this->get(route('admin.messages.index', ['channel' => 'crew', 'mode' => 'new', 'q' => substr($crew->email, 0, 4)]))->assertOk()->assertSee($crew->email);
        $this->actingAs($crew)->post(route('customer.messages.reply', $thread), ['message' => 'Briefing received.'])->assertRedirect();
        $this->assertSame('pending', $thread->fresh()->status);
        $this->actingAs($admin)->get(route('admin.messages.index', ['channel' => 'crew', 'thread' => $thread->uuid]))->assertOk()->assertSee('Briefing received.')->assertSee('data-notification-key="message-channel-crew"', false);
        $this->post(route('admin.messages.send'), ['channel' => 'customer', 'thread' => $thread->uuid, 'body' => 'Wrong audience'])->assertNotFound();
        $this->post(route('admin.messages.send'), ['channel' => 'crew', 'thread' => $thread->uuid, 'body' => 'Thank you.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('resolved', $thread->fresh()->status);
        $this->assertSame(1, $chat->messages()->count());
    }

    public function test_admin_shortcuts_sidebar_placement_and_old_entry_points(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Identifiable Administrator']);
        CrewProfile::factory()->for($admin)->create();
        $page = $this->actingAs($admin)->get(route('admin.messages.index'))->assertOk();
        $doc = new \DOMDocument;
        @$doc->loadHTML($page->getContent());
        $xpath = new \DOMXPath($doc);
        $this->assertSame(['Event availability', 'Concert tracker', 'My Hub', 'My Media', 'Message Centre'], array_map(fn ($node) => $node->getAttribute('aria-label'), iterator_to_array($xpath->query('//nav[@aria-label="Admin quick access"]/a'))));
        $this->assertStringNotContainsString($admin->name, $xpath->query('//header[@class="topbar"]')->item(0)->textContent);
        foreach (['Hub management' => ['My Hub'], 'Media management' => ['My Media', 'Concert tracker']] as $group => $items) {
            $node = $xpath->query('//nav[@id="admin-navigation"]/details[summary[contains(.,"'.$group.'")]]')->item(0);
            $this->assertNotNull($node);
            foreach ($items as $item) {
                $this->assertStringContainsString($item, $node->textContent);
            }
        }
        $this->assertStringNotContainsString('Studio messages', $xpath->query('//nav[@id="admin-navigation"]')->item(0)->textContent);
        $this->assertStringNotContainsString('Customer support', $xpath->query('//nav[@id="admin-navigation"]')->item(0)->textContent);
        $this->get(route('admin.media-support.index'))->assertRedirect(route('admin.messages.index', ['channel' => 'customer', 'status' => 'pending']));
        $this->get(route('admin.concert-tracker.studio-communications.index'))->assertRedirect(route('admin.messages.index', ['channel' => 'studio']));
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.messages.index'))->assertForbidden();
    }
}
