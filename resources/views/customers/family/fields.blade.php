<div class="family-fields">
    <div class="family-names">
        <label>First name<input name="first_name" required maxlength="255" autocomplete="off" value="{{ $member?->user_id ? $member->user->first_name : $value('first_name', $member?->first_name) }}" @readonly($member?->user_id)></label>
        <label>Last name<input name="last_name" required maxlength="255" autocomplete="off" value="{{ $member?->user_id ? $member->user->last_name : $value('last_name', $member?->last_name) }}" @readonly($member?->user_id)></label>
    </div>
    @if($member?->user_id)<p class="meta">Name and email follow their linked account. They can edit these in My Profile → My details.</p>@endif
    <label data-family-email @if(!$value('can_view_media', $member?->can_view_media) && !$value('can_purchase', $member?->can_purchase)) hidden @endif>Email address <span class="meta">Required for account access</span><input type="email" name="email" maxlength="255" value="{{ $value('email', $member?->user?->email ?? $member?->email) }}" @readonly($member?->user_id)></label>
</div>
