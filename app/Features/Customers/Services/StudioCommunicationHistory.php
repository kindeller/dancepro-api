<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerStudio;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Features\Studios\Models\StudioAccountAccess;
use App\Features\Tracking\Mail\StudioConversationReply;
use App\Features\Tracking\Models\ConcertTrackerEmailDelivery;
use App\Features\Tracking\Models\ConcertTrackerInboundEmail;
use App\Features\Tracking\Models\StudioConversationAttachment;
use App\Features\Tracking\Models\StudioConversationMessage;
use App\Features\Tracking\Models\StudioConversationThread;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class StudioCommunicationHistory
{
    public function inbox(): array
    {
        $studioIds = StudioConversationMessage::query()->pluck('studio_id')
            ->merge(ConcertTrackerEmailDelivery::query()->whereNotNull('scheduling_event_id')->with('event:id,studio_id')->get()->pluck('event.studio_id'))
            ->filter()->unique();
        $studios = Studio::query()->whereIn('id', $studioIds)->with(['contacts', 'concerts'])->orderBy('name')->get();
        $latest = StudioConversationMessage::query()->with('event')->latest('sent_at')->get()->unique('studio_id')->keyBy('studio_id');
        $threads = StudioConversationThread::query()->with('assignedUser')->whereIn('studio_id', $studioIds)->get()->keyBy('studio_id');
        $unread = StudioConversationMessage::query()->where('direction', 'to_dancepro')->whereNull('staff_read_at')
            ->selectRaw('studio_id, count(*) as total')->groupBy('studio_id')->pluck('total', 'studio_id');

        return compact('studios', 'latest', 'unread', 'threads');
    }

    public function forUser(User $user, Studio $studio): array
    {
        abort_unless($this->canView($user, $studio), 403);

        StudioConversationMessage::query()->where('studio_id', $studio->id)
            ->where('direction', 'from_dancepro')->whereNull('studio_read_at')->update(['studio_read_at' => now()]);

        $deliveries = ConcertTrackerEmailDelivery::query()
            ->with('event')
            ->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))
            ->whereIn('status', ['queued', 'scheduled', 'sent'])
            ->orderByDesc('sent_at')->orderByDesc('scheduled_at')->orderByDesc('created_at')
            ->get();
        $inbound = ConcertTrackerInboundEmail::query()
            ->with('event')
            ->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))
            ->orderByDesc('received_at')->get();

        $webMessages = StudioConversationMessage::query()->with(['event', 'attachments'])->where('studio_id', $studio->id)->get();
        $communications = $deliveries->map(fn ($delivery): array => [
            'direction' => 'from_dancepro', 'subject' => $delivery->subject, 'body' => $delivery->body,
            'contact' => $delivery->recipient_email, 'event' => $delivery->event?->name,
            'occurred_at' => $delivery->sent_at ?? $delivery->scheduled_at ?? $delivery->created_at,
            'status' => $delivery->status, 'attachments' => collect(),
        ])->concat($inbound->map(fn ($message): array => [
            'direction' => 'to_dancepro', 'subject' => $message->subject, 'body' => $message->body,
            'contact' => $message->sender_email, 'event' => $message->event?->name,
            'occurred_at' => $message->received_at, 'status' => 'received', 'attachments' => collect(),
        ]))->concat($webMessages->map(fn ($message): array => [
            'direction' => $message->direction, 'subject' => $message->subject, 'body' => $message->body,
            'contact' => $message->sender_email ?: $message->recipient_email, 'event' => $message->event?->name,
            'occurred_at' => $message->sent_at, 'status' => 'sent in DancePro', 'attachments' => $message->attachments,
        ]))->sortByDesc('occurred_at')->values();

        $events = $this->studioEvents($studio);

        return compact('studio', 'communications', 'events');
    }

    public function reply(User $user, Studio $studio, array $data): StudioConversationMessage
    {
        abort_unless($this->canView($user, $studio), 403);
        $event = $this->event($studio, $data['event_uuid'] ?? null);

        $message = StudioConversationMessage::create([
            'studio_id' => $studio->id, 'scheduling_event_id' => $event?->id, 'sender_user_id' => $user->id,
            'direction' => 'to_dancepro', 'channel' => 'web', 'sender_name' => $user->name,
            'sender_email' => $user->email, 'subject' => ($data['subject'] ?? null) ?: 'Message from '.$studio->name,
            'body' => $data['body'], 'sent_at' => now(), 'studio_read_at' => now(),
        ]);
        $this->attach($message, $data['attachment'] ?? null);

        return $message;
    }

    public function adminPage(Studio $studio): array
    {
        StudioConversationMessage::query()->where('studio_id', $studio->id)
            ->where('direction', 'to_dancepro')->whereNull('staff_read_at')->update(['staff_read_at' => now()]);

        $thread = StudioConversationThread::firstOrCreate(['studio_id' => $studio->id]);
        $staff = User::query()->where('is_admin', true)->where('is_active', true)->orderBy('name')->get();

        return $this->forUserData($studio) + ['contactEmails' => $this->contactEmails($studio), 'thread' => $thread->load('assignedUser'), 'staff' => $staff];
    }

    public function adminReply(User $user, Studio $studio, array $data): StudioConversationMessage
    {
        $event = $this->event($studio, $data['event_uuid'] ?? null);
        $recipient = mb_strtolower(trim($data['recipient_email'] ?? ''));
        abort_unless($this->contactEmails($studio)->contains($recipient), 422);
        $message = StudioConversationMessage::create([
            'studio_id' => $studio->id, 'scheduling_event_id' => $event?->id, 'sender_user_id' => $user->id,
            'direction' => 'from_dancepro', 'channel' => 'web_and_email', 'sender_name' => $user->name,
            'sender_email' => $user->email, 'recipient_email' => $recipient,
            'subject' => ($data['subject'] ?? null) ?: 'Message from DancePro', 'body' => $data['body'],
            'sent_at' => now(), 'staff_read_at' => now(),
        ]);
        $this->attach($message, $data['attachment'] ?? null);
        Mail::to($recipient)->send(new StudioConversationReply(
            $message->subject, $message->body, route('customer.studio-communications.index', $studio),
            $user->id,
        ));

        return $message;
    }

    public function assign(Studio $studio, ?int $userId): void
    {
        StudioConversationThread::updateOrCreate(['studio_id' => $studio->id], ['assigned_user_id' => $userId]);
    }

    public function canView(User $user, Studio $studio): bool
    {
        $email = mb_strtolower(trim($user->email));
        $access = StudioAccountAccess::query()->where('studio_id', $studio->id)->where('email', $email)->first();
        if ($access?->status === 'revoked') {
            return false;
        }
        if ($access && $user->hasVerifiedEmail()) {
            if ($access->status !== 'active' || $access->user_id !== $user->id) {
                $access->update(['user_id' => $user->id, 'status' => 'active', 'activated_at' => now(), 'revoked_at' => null]);
            }
            CustomerStudio::updateOrCreate(['user_id' => $user->id, 'studio_id' => $studio->id], ['access_verified_at' => now()]);

            return true;
        }
        if (! $user->canAccessCustomer() || ! CustomerStudio::query()->where('user_id', $user->id)
            ->where('studio_id', $studio->id)->whereNotNull('access_verified_at')->exists()) {
            return false;
        }

        return $this->contactEmails($studio)->contains($email);
    }

    private function contactEmails(Studio $studio): Collection
    {
        $studio->loadMissing(['contacts', 'accountAccesses']);

        return $studio->contacts->flatMap->emailAddresses()
            ->merge($studio->accountAccesses->where('status', 'active')->pluck('email'))
            ->push($studio->contact_email)->filter()
            ->map(fn (string $email): string => mb_strtolower(trim($email)))->unique();
    }

    private function event(Studio $studio, ?string $uuid): mixed
    {
        if (! $uuid) {
            return null;
        }

        return $studio->concerts()->whereHas('bookingItem.schedulingEvent', fn ($events) => $events->where('uuid', $uuid))
            ->with('bookingItem.schedulingEvent')->firstOrFail()->bookingItem->schedulingEvent;
    }

    private function forUserData(Studio $studio): array
    {
        $deliveries = ConcertTrackerEmailDelivery::with('event')->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))->whereIn('status', ['queued', 'scheduled', 'sent'])->get();
        $inbound = ConcertTrackerInboundEmail::with('event')->whereHas('event', fn ($events) => $events->where('studio_id', $studio->id))->get();
        $web = StudioConversationMessage::with(['event', 'attachments'])->where('studio_id', $studio->id)->get();
        $communications = $deliveries->map(fn ($m) => ['direction' => 'from_dancepro', 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->recipient_email, 'event' => $m->event?->name, 'occurred_at' => $m->sent_at ?? $m->created_at, 'status' => $m->status, 'attachments' => collect()])
            ->concat($inbound->map(fn ($m) => ['direction' => 'to_dancepro', 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->sender_email, 'event' => $m->event?->name, 'occurred_at' => $m->received_at, 'status' => 'received', 'attachments' => collect()]))
            ->concat($web->map(fn ($m) => ['direction' => $m->direction, 'subject' => $m->subject, 'body' => $m->body, 'contact' => $m->sender_email ?: $m->recipient_email, 'event' => $m->event?->name, 'occurred_at' => $m->sent_at, 'status' => 'sent in DancePro', 'attachments' => $m->attachments]))
            ->sortByDesc('occurred_at')->values();
        $events = $this->studioEvents($studio);

        return compact('studio', 'communications', 'events');
    }

    private function attach(StudioConversationMessage $message, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }
        $key = $file->store('studio-communications/'.$message->studio_id.'/'.$message->uuid, 'local');
        StudioConversationAttachment::create([
            'studio_conversation_message_id' => $message->id, 'disk' => 'local', 'storage_key' => $key,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
        ]);
    }

    private function studioEvents(Studio $studio): Collection
    {
        return SchedulingEvent::query()->where('studio_id', $studio->id)
            ->with(['concertBookingItem', 'roleRequirements.crewRole', 'roleSeats', 'studioFiles.uploadedBy'])
            ->orderByDesc('event_date')->get();
    }
}
