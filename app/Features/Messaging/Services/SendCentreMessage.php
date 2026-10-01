<?php

namespace App\Features\Messaging\Services;

use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Services\CustomerAccountAdministration;
use App\Features\Customers\Services\CustomerConversations;
use App\Features\Customers\Services\StudioCommunicationHistory;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SendCentreMessage
{
    public function __construct(
        private readonly MessageRecipients $recipients,
        private readonly CustomerConversations $customers,
        private readonly CustomerAccountAdministration $accounts,
        private readonly StudioCommunicationHistory $studios,
    ) {}

    public function send(User $actor, array $input): array
    {
        $channel = $input['channel'];
        $threadId = $input['thread'] ?? null;
        if ($channel === 'studio') {
            Gate::forUser($actor)->authorize('manageScheduling');
            $studio = Studio::where('uuid', $threadId ?: $input['recipient'])->firstOrFail();
            $emails = $this->studios->contactEmails($studio);
            $recipientEmail = $input['recipient_email'] ?? $emails->first();
            if (! $recipientEmail || ! $emails->contains(mb_strtolower($recipientEmail))) {
                throw ValidationException::withMessages(['recipient_email' => 'Choose an active studio contact before sending a message.']);
            }
            $this->studios->adminReply($actor, $studio, array_merge($input, ['recipient_email' => $recipientEmail]));
            $threadId = $studio->uuid;
        } else {
            if ($threadId) {
                $thread = CustomerSupportRequest::forMessageAudience($channel)->where('uuid', $threadId)->firstOrFail();
                $this->customers->reply($thread, $actor, ['message' => $input['body'], 'attachment' => $input['attachment'] ?? null], true);
            } else {
                $recipient = $this->recipients->users($channel, $actor)->where('uuid', $input['recipient'])->firstOrFail();
                $email = $this->accounts->email($recipient, $actor, $input['subject'], $input['body'], $input['attachment'] ?? null, $channel);
                $thread = CustomerSupportRequest::where('customer_account_email_id', $email->id)->firstOrFail();
            }
            $threadId = $thread->uuid;
        }

        return ['channel' => $channel, 'thread' => $threadId];
    }
}
