<?php

namespace App\Features\Customers\Services;

use App\Features\Customers\Models\CustomerSupportMessage;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Notifications\CustomerConversationReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CustomerConversations
{
    public function syncHistory(CustomerSupportRequest $request): void
    {
        if (! Schema::hasTable('customer_support_messages') || $request->user?->anonymized_at) {
            return;
        }
        $fromStaff = $request->customer_account_email_id !== null;
        $initial = $request->messages()->firstOrCreate(['source_key' => 'request:'.$request->id], [
            'author_user_id' => $fromStaff ? $request->decided_by_user_id : $request->user_id,
            'direction' => $fromStaff ? 'to_customer' : 'to_dancepro',
            'body' => $request->message, 'sent_at' => $request->requested_at,
        ]);
        if ($initial->wasRecentlyCreated && ! $fromStaff) {
            $request->customer_read_at = $request->requested_at;
        }
        if ($request->decided_at && ! $fromStaff && in_array($request->status, ['approved', 'declined', 'resolved'], true)
            && ($request->wasChanged(['decided_at', 'response_note', 'status']) || ! $request->messages()->where('source_key', 'like', 'decision:%')->exists())) {
            $decision = $request->messages()->firstOrCreate(['source_key' => 'decision:'.$request->id.':'.hash('sha256', $request->decided_at->toIso8601String().$request->status.$request->response_note)], [
                'author_user_id' => $request->decided_by_user_id, 'direction' => 'to_customer',
                'body' => ($request->response_note ?: 'Your request has been '.$request->status.'.'), 'sent_at' => $request->decided_at,
            ]);
        }
        if (isset($decision) && $decision->wasRecentlyCreated) {
            $request->customer_read_at = null;
        }
        $request->last_activity_at = $request->messages()->max('sent_at');
        $request->latest_staff_message_at = $request->messages()->where('direction', 'to_customer')->max('sent_at');
        $request->saveQuietly();
    }

    public function assertOwner(CustomerSupportRequest $conversation, User $user): void
    {
        abort_unless($user->canAccessCustomer() && $conversation->user_id === $user->id, 404);
    }

    public function page(CustomerSupportRequest $conversation, User $user, bool $admin = false): array
    {
        $admin ? abort_unless($user->canAccessAdmin(), 403) : $this->assertOwner($conversation, $user);
        $conversation = DB::transaction(function () use ($conversation, $admin) {
            $thread = CustomerSupportRequest::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $this->syncHistory($thread);
            if (! $admin) {
                // Serialize reading and replies so a concurrent new reply stays unread.
                $thread->forceFill(['customer_read_at' => now()])->saveQuietly();
            }

            return $thread->load(['messages', 'order', 'user', 'accountEmail']);
        });

        return ['conversation' => $conversation, 'adminConversation' => $admin];
    }

    public function reply(CustomerSupportRequest $conversation, User $user, array $input, bool $admin = false): void
    {
        $admin ? abort_unless($user->canAccessAdmin(), 403) : $this->assertOwner($conversation, $user);
        $message = DB::transaction(function () use ($conversation, $user, $input, $admin) {
            $thread = CustomerSupportRequest::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $message = $thread->messages()->create([
                'author_user_id' => $user->id, 'direction' => $admin ? 'to_customer' : 'to_dancepro',
                'body' => $input['message'], 'sent_at' => now(),
            ]);
            $this->attach($message, $input['attachment'] ?? null);
            $thread->forceFill(['status' => $admin ? ($thread->type === 'download_reactivation' ? $thread->status : 'resolved') : 'pending', 'last_activity_at' => $message->sent_at,
                'latest_staff_message_at' => $admin ? $message->sent_at : $thread->latest_staff_message_at,
                'customer_read_at' => $admin ? null : now(),
            ])->saveQuietly();

            return $message;
        });
        if ($admin) {
            $conversation->user?->notify((new CustomerConversationReply($conversation, $message->body))->afterCommit());
        }
    }

    public function attach(CustomerSupportMessage $message, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }
        $path = $file->store('customer-messages/'.$message->uuid, 'local');
        throw_unless($path, \RuntimeException::class, 'The attachment could not be saved. Please try again.');
        $message->update(['attachment' => ['path' => $path, 'name' => basename($file->getClientOriginalName()), 'size' => $file->getSize()]]);
    }

    public function attachment(CustomerSupportMessage $message, User $user, bool $admin = false)
    {
        $admin ? abort_unless($user->canAccessAdmin(), 403) : $this->assertOwner($message->conversation, $user);
        abort_unless($message->attachment && Storage::disk('local')->exists($message->attachment['path']), 404);

        return Storage::disk('local')->download($message->attachment['path'], $message->attachment['name'], ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
