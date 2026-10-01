@php
    $permissionInfo = [
        'manage' => ['Add/remove family members', 'Only the account manager can add or remove family members, send invitations and change permissions. This permission is locked.'],
        'browse' => ['Access galleries, favourites & cart', 'Included with account access and cannot be switched off separately. Gallery access codes and release rules still apply. Making a payment requires Make purchases.'],
        'can_view_media' => ['View & download purchased media', 'Access eligible purchases made by accepted family members, including earlier purchases. Original release and download expiry rules still apply.'],
        'can_purchase' => ['Make purchases', 'Buy media using this person’s own account and payment details. Everyone with account access can save favourites and build a cart.'],
        'is_dancer' => ['Dancer', 'Use this person’s name to find competition routines. A dancer-only profile has no sign-in or invitation. Add email and account permissions later when they are ready.'],
    ];
@endphp
<fieldset class="family-permissions" aria-label="Family permissions">
@foreach($permissionInfo as $key => [$label, $description])
    @continue($key === 'manage' && !$managerForm)
    @php($locked = in_array($key, ['manage', 'browse']) || ($managerForm && $key !== 'is_dancer'))
    @php($hintId = 'family-'.($member?->uuid ?? 'new').'-'.$key.'-hint')
    <div class="family-permission" @if($key === 'browse') data-family-browse @if(!$managerForm && !$value('can_view_media', $member?->can_view_media) && !$value('can_purchase', $member?->can_purchase)) hidden @endif @endif>
        <label>
            @if(!in_array($key, ['manage', 'browse']) && !$readOnly)<input type="hidden" name="{{ $key }}" value="0">@endif
            <input type="checkbox" name="{{ $key }}" value="1" aria-describedby="{{ $hintId }}" @checked($locked || $value($key, $member?->$key ?? false)) @disabled($locked || $readOnly)>
            <span>{{ $label }}</span>
        </label>
        <button class="family-help" type="button" aria-label="About {{ $label }}" aria-describedby="{{ $hintId }}">i</button>
        <span class="family-tip" id="{{ $hintId }}" role="tooltip">{{ $description }}@if($managerForm && $locked && !in_array($key, ['manage', 'browse'])) Always enabled for the account manager.@endif</span>
    </div>
@endforeach
</fieldset>
