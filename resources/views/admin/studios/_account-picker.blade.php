<div class="studio-account-picker" style="margin:14px 0">
    <label>Find a customer account<input type="search" class="studio-account-search" autocomplete="off" maxlength="255" value="{{ $contact['account_label'] ?? ($contact['account_uuid'] ?? '') }}" placeholder="Start typing a name, email or account ID" aria-label="Find a customer account" aria-expanded="false"></label>
    <input type="hidden" class="studio-account-uuid" name="contacts[{{ $index }}][account_uuid]" value="{{ $contact['account_uuid'] ?? '' }}">
    <div class="studio-account-results" hidden></div>
    <p class="studio-account-search-status muted" role="status" aria-live="polite"></p>
    <label>Studio Hub access<select class="studio-account-access" name="contacts[{{ $index }}][access_role]">
        <option value="">Contact only / keep existing access</option>
        @foreach(['owner'=>'Owner', 'director'=>'Director', 'office_contact'=>'Office contact'] as $value=>$label)
            <option value="{{ $value }}" @selected(($contact['access_role'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select></label>
    <p class="muted">Select an account to fill their contact details. Choose an access role to invite or update them when you save. Unverified accounts stay pending until email verification.</p>
</div>
