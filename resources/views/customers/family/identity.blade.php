@php($state = $member->status === 'pending' && $member->expires_at?->isPast() ? 'expired' : $member->status)
<div class="family-identity-top"><strong>{{ $member->displayName() }}</strong><span class="family-status {{ $state }}">{{ match($state) { 'active' => 'Active', 'dancer' => 'Dancer only', 'pending' => 'Invited', 'expired' => 'Invitation expired', default => 'Not invited' } }}</span></div>
@if($managerForm)<div class="meta">Account manager</div>@endif
@if($member->email)<div class="meta">{{ $member->user?->email ?? $member->email }}</div>@endif
