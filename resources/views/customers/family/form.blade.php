@php($editing = old('action') === ($managerForm ? 'manager' : 'save') && old('member_uuid') === $member?->uuid)
@php($value = fn($key, $default = null) => $editing ? old($key, $default) : $default)
@php($invitationState = $member?->status === 'pending' && $member->expires_at?->isPast() ? 'expired' : ($member?->status ?? 'new'))
@php($needsInvite = !$managerForm && !in_array($invitationState, ['active', 'pending']) && ($value('can_view_media', $member?->can_view_media) || $value('can_purchase', $member?->can_purchase)))
<form id="family-form-{{ $member?->uuid ?? 'new' }}" class="family-form" data-preserve-scroll-form="{{ route('customer.profile.edit', ['tab' => 'family']) }}" data-scroll-group="Profile sections" data-invitation-state="{{ $managerForm ? 'active' : $invitationState }}" data-original-view="{{ $member?->can_view_media ? '1' : '0' }}" data-original-purchase="{{ $member?->can_purchase ? '1' : '0' }}" data-original-email="{{ $member?->email }}" method="POST" action="{{ route('customer.family.store') }}">@csrf
    <input type="hidden" name="action" value="{{ $managerForm ? 'manager' : 'save' }}">
    @if($member)<input type="hidden" name="member_uuid" value="{{ $member->uuid }}">@endif
    <div class="family-identity">
        @if($member)@include('customers.family.identity')@endif
        @if(!$managerForm)
            @if($member)
                <details class="family-details" @if($editing) open @endif><summary>Edit details</summary>@include('customers.family.fields')</details>
            @else
                @include('customers.family.fields')
            @endif
        @endif
    </div>
    @include('customers.family.permissions', ['readOnly' => false])
    <div class="family-save">
        <button class="button" data-family-save data-default-label="{{ $member ? 'Save' : 'Add member' }}">{{ $needsInvite ? 'Save & send invite' : ($member ? 'Save' : 'Add member') }}</button>
        @if($member && !$managerForm)
            <button type="button" class="family-trash" data-family-remove="family-remove-{{ $member->uuid }}" data-member-name="{{ $member->displayName() }}" aria-label="Remove {{ $member->displayName() }}" title="Remove family member"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>
        @endif
    </div>
    <details class="family-search" data-family-search @if(!$value('is_dancer', $member?->is_dancer ?? false)) hidden @endif>
        <summary>Competition search names</summary>
        <p class="meta">Alternative programme spellings, one per line. These stay with the dancer if you add account access later.</p>
        <label>Other names<textarea name="programme_names" rows="2">{{ is_array($value('programme_names', $member?->dancer?->programme_names ?? [])) ? implode("\n", $value('programme_names', $member?->dancer?->programme_names ?? [])) : $value('programme_names') }}</textarea></label>
    </details>
</form>
