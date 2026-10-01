<?php

namespace App\Features\Messaging\Services;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerConversations;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class MessageCentre
{
    public function __construct(
        private readonly MessageRecipients $recipients,
        private readonly CustomerConversations $customers,
        private readonly StudioCommunicationHistory $studios,
    ) {}

    public function page(User $user, array $input): array
    {
        // Existing links without a channel still open their customer thread.
        $channel = $input['channel'] ?? (isset($input['thread']) || isset($input['recipient']) ? 'customer' : 'all');
        $mode = $input['mode'] ?? 'inbox';
        $q = trim($input['q'] ?? '');
        $status = $input['status'] ?? 'all';
        if ($channel === 'studio') {
            Gate::forUser($user)->authorize('manageScheduling');
        }
        $base = compact('channel', 'mode', 'q', 'status') + ['recipient' => null, 'results' => collect()];
        if ($mode === 'new') {
            $channels = $channel === 'all' ? ['customer', 'studio', 'crew'] : [$channel];
            foreach ($channels as $audience) {
                if ($audience === 'studio' && Gate::forUser($user)->denies('manageScheduling')) {
                    continue;
                }
                $base['results'] = $base['results']->concat($this->recipients->search($audience, $q, $user)->map(fn ($item) => $item + ['channel' => $audience]));
            }
            if (($input['recipient'] ?? null) && $channel !== 'all') {
                $base['recipient'] = ($channel === 'studio' ? Studio::query() : $this->recipients->users($channel, $user))
                    ->where('uuid', $input['recipient'])->firstOrFail();
                if ($channel === 'studio') {
                    $base += $this->studios->adminPage($base['recipient']);
                }
            }

            return $base;
        }

        $items = collect();
        $selected = [];
        if ($channel !== 'studio') {
            $audiences = $channel === 'all' ? ['customer', 'crew'] : [$channel];
            foreach ($audiences as $audience) {
                $matching = $q === '' ? collect() : $this->recipients->search($audience, $q, $user)->pluck('reference');
                $threads = CustomerSupportRequest::with(['user', 'order'])->forMessageAudience($audience)
                    ->when(in_array($status, ['pending', 'approved', 'declined', 'resolved']), fn ($query) => $query->where('status', $status))
                    ->when($status === 'unread', fn ($query) => $query->where('status', 'pending'))
                    ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query->where('subject', 'like', '%'.$q.'%')->orWhere('uuid', $q)
                        ->orWhereHas('user', fn ($query) => $query->whereIn('uuid', $matching))))
                    ->get();
                $items = $items->concat($threads->map(fn ($thread) => [
                    'channel' => $audience, 'reference' => $thread->uuid, 'name' => $thread->user?->name ?? 'Archived account',
                    'detail' => $thread->user?->email, 'subject' => $thread->subject,
                    'status' => $thread->status === 'pending' ? 'Awaiting reply' : ucfirst($thread->status),
                    'at' => $thread->last_activity_at ?? $thread->requested_at,
                ]));
            }
            if (($input['thread'] ?? null) && $channel !== 'all') {
                $selected = $this->customers->page(CustomerSupportRequest::forMessageAudience($channel)->where('uuid', $input['thread'])->firstOrFail(), $user, true);
            }
        }
        if (in_array($channel, ['all', 'studio']) && Gate::forUser($user)->allows('manageScheduling')) {
            $data = $this->studios->inbox();
            foreach ($data['studios'] as $studio) {
                $latest = $data['latest']->get($studio->id);
                if ($q !== '' && ! str_contains(mb_strtolower($studio->name.' '.$studio->contact_email.' '.$studio->uuid.' '.$latest?->subject), mb_strtolower($q))) {
                    continue;
                }
                $unread = $data['unread'][$studio->id] ?? 0;
                if (in_array($status, ['unread', 'pending']) && ! $unread) {
                    continue;
                }
                if (in_array($status, ['approved', 'declined', 'resolved'])) {
                    continue;
                }
                $items->push(['channel' => 'studio', 'reference' => $studio->uuid, 'name' => $studio->name, 'detail' => $studio->contact_email,
                    'subject' => $latest?->subject ?? 'Studio email history', 'status' => $unread ? $unread.' unread' : 'Read', 'at' => $latest?->sent_at]);
            }
            if (($input['thread'] ?? null) && $channel === 'studio') {
                $selected = $this->studios->adminPage(Studio::where('uuid', $input['thread'])->firstOrFail());
                $items = $items->map(fn ($item) => $item['channel'] === 'studio' && $item['reference'] === $input['thread'] ? array_replace($item, ['status' => 'Read']) : $item);
            }
        }
        $items = $items->sortByDesc(fn ($item) => $item['at']?->getTimestamp() ?? 0)->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $threads = (new LengthAwarePaginator($items->forPage($page, 25)->values(), $items->count(), 25, $page, ['path' => route('admin.messages.index')]))->withQueryString();

        return $base + compact('threads') + $selected;
    }
}
