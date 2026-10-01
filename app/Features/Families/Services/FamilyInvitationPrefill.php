<?php

namespace App\Features\Families\Services;

use App\Features\Families\Models\CustomerFamilyMember;
use Illuminate\Contracts\Session\Session;

class FamilyInvitationPrefill
{
    public function remember(Session $session, CustomerFamilyMember $member): void
    {
        $session->put('family_invitation_prefill', [
            'member_uuid' => $member->uuid,
            'invitation_hash' => $member->invitation_hash,
        ]);
    }

    public function details(Session $session): array
    {
        $context = $session->get('family_invitation_prefill');
        if (! is_array($context)) {
            return [];
        }
        $member = CustomerFamilyMember::where('uuid', $context['member_uuid'] ?? '')
            ->where('status', 'pending')->where('expires_at', '>', now())->first();
        if (! $member?->invitation_hash || ! hash_equals($member->invitation_hash, (string) ($context['invitation_hash'] ?? ''))) {
            $session->forget('family_invitation_prefill');

            return [];
        }

        return $member->only(['first_name', 'last_name', 'email']);
    }
}
