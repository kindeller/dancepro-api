@php
    $crewProfile = $crewProfile ?? null;
    $existingQualifications = $crewProfile?->roleQualifications?->keyBy('crew_role_id') ?? collect();
@endphp

<div class="grid two-col">
    <div class="grid">
        <label>Preferred name<input name="preferred_name" value="{{ old('preferred_name', $crewProfile?->preferred_name) }}" required maxlength="255"></label>
        <label>Legal name<input name="legal_name" value="{{ old('legal_name', $crewProfile?->legal_name) }}" maxlength="255"></label>
        <label>Email<input type="email" name="email" value="{{ old('email', $crewProfile?->user?->email) }}" required maxlength="255"></label>
        <label>Mobile<input name="phone" value="{{ old('phone', $crewProfile?->phone) }}" required maxlength="50"></label>
    </div>
        <div class="grid">
            <label>Commencement date<input type="date" name="commencement_date" value="{{ old('commencement_date', $crewProfile?->commencement_date?->toDateString()) }}" required max="{{ today()->toDateString() }}"></label>
        @if($crewProfile?->commencement_date)
            <div class="notice"><strong>Time with DancePro:</strong> {{ $crewProfile->completedYearsOfService() }} years ({{ $crewProfile->monthsOfService() }} completed months)</div>
        @endif
        <label>Shirt size<input name="shirt_size" value="{{ old('shirt_size', $crewProfile?->shirt_size) }}" maxlength="50" placeholder="e.g. M"></label>
        <label>Jacket size<input name="jacket_size" value="{{ old('jacket_size', $crewProfile?->jacket_size) }}" maxlength="50" placeholder="e.g. M"></label>
        <label><span>Employment status</span><select name="is_active"><option value="1" @selected(old('is_active', $crewProfile?->user?->is_active ?? true))>Active</option><option value="0" @selected(!old('is_active', $crewProfile?->user?->is_active ?? true))>Inactive</option></select></label>
    </div>
</div>

<div class="card card-pad" style="margin-top:20px">
    <h2>Personal and emergency details</h2>
    <div class="grid two-col" style="margin-top:16px">
        <div class="grid">
            <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $crewProfile?->date_of_birth) }}"></label>
            <label>Address line 1<input name="address_line_1" value="{{ old('address_line_1', $crewProfile?->address_line_1) }}" maxlength="255"></label>
            <label>Address line 2<input name="address_line_2" value="{{ old('address_line_2', $crewProfile?->address_line_2) }}" maxlength="255"></label>
            <div class="grid" style="grid-template-columns:2fr 1fr 1fr"><label>Suburb<input name="suburb" value="{{ old('suburb', $crewProfile?->suburb) }}"></label><label>State<input name="state" value="{{ old('state', $crewProfile?->state) }}"></label><label>Postcode<input name="postcode" value="{{ old('postcode', $crewProfile?->postcode) }}"></label></div>
        </div>
        <div class="grid">
            <label>Emergency contact name<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $crewProfile?->emergency_contact_name) }}"></label>
            <label>Relationship<input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $crewProfile?->emergency_contact_relationship) }}"></label>
            <label>Emergency contact phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $crewProfile?->emergency_contact_phone) }}"></label>
            <label>Usual travel area<input name="usual_travel_area" value="{{ old('usual_travel_area', $crewProfile?->usual_travel_area) }}" placeholder="e.g. Perth metro and Peel"></label>
        </div>
    </div>
</div>

<div class="card card-pad" style="margin-top:20px">
    <h2>Payment details</h2>
    <p class="muted">These values are encrypted before they are stored.</p>
    <div class="grid two-col">
        <div class="grid"><label>ABN<input name="abn" value="{{ old('abn', $crewProfile?->abn) }}"></label><label>Bank account name<input name="bank_account_name" value="{{ old('bank_account_name', $crewProfile?->bank_account_name) }}"></label><label>Bank<input name="bank_name" value="{{ old('bank_name', $crewProfile?->bank_name) }}"></label><div class="grid" style="grid-template-columns:1fr 2fr"><label>BSB<input name="bank_bsb" value="{{ old('bank_bsb', $crewProfile?->bank_bsb) }}"></label><label>Account number<input name="bank_account_number" value="{{ old('bank_account_number', $crewProfile?->bank_account_number) }}"></label></div></div>
    </div>
</div>

<div class="card card-pad" style="margin-top:20px">
    <h2>Safety, checks, and requirements</h2>
    <div class="notice" style="margin-top:16px"><strong>Working With Children Check:</strong> keep the card number and expiry date current. These details are important for crew eligibility.</div>
    <div class="grid two-col" style="margin-top:16px">
        <div class="grid"><label>Working With Children Check number<input name="working_with_children_number" value="{{ old('working_with_children_number', $crewProfile?->working_with_children_number) }}"></label><label>WWCC expiry<input type="date" name="working_with_children_expiry" value="{{ old('working_with_children_expiry', $crewProfile?->working_with_children_expiry?->toDateString()) }}"></label></div>
        <div class="grid"><label>Dietary requirements<textarea name="dietary_requirements" style="min-height:100px;font-family:inherit">{{ old('dietary_requirements', $crewProfile?->dietary_requirements) }}</textarea></label><label>Medical information and allergies<textarea name="medical_information" style="min-height:100px;font-family:inherit">{{ old('medical_information', $crewProfile?->medical_information) }}</textarea></label><label>Equipment they own<textarea name="owned_equipment" style="min-height:100px;font-family:inherit">{{ old('owned_equipment', $crewProfile?->owned_equipment) }}</textarea></label><label>Internal notes<textarea name="internal_notes" style="min-height:100px;font-family:inherit">{{ old('internal_notes', $crewProfile?->internal_notes) }}</textarea></label></div>
    </div>
</div>

<div class="card card-pad" style="margin-top:20px">
    <h2>Vehicles</h2>
    <p class="muted">Up to ten vehicles can be stored. Leave an unused row blank.</p>
    @php($vehicleRows = old('vehicles', $crewProfile?->vehicles?->toArray() ?? []))
    @for($index = 0; $index < max(3, count($vehicleRows) + 1); $index++)
        @php($vehicle = $vehicleRows[$index] ?? [])
        <div class="grid" style="grid-template-columns:repeat(4,minmax(120px,1fr)) 2fr;border-top:1px solid var(--line);padding-top:14px;margin-top:14px">
            <input type="hidden" name="vehicles[{{ $index }}][uuid]" value="{{ data_get($vehicle, 'uuid') }}">
            <label>Make<input name="vehicles[{{ $index }}][make]" value="{{ data_get($vehicle, 'make') }}"></label>
            <label>Model<input name="vehicles[{{ $index }}][model]" value="{{ data_get($vehicle, 'model') }}"></label>
            <label>Registration<input name="vehicles[{{ $index }}][registration]" value="{{ data_get($vehicle, 'registration') }}"></label>
            <label>Colour<input name="vehicles[{{ $index }}][colour]" value="{{ data_get($vehicle, 'colour') }}"></label>
            <label>Notes<input name="vehicles[{{ $index }}][notes]" value="{{ data_get($vehicle, 'notes') }}"></label>
        </div>
    @endfor
</div>

<div class="card card-pad" style="margin-top:20px">
    <h2>Role qualifications</h2>
    <p class="muted">Leave a role blank when it does not apply. Training identifies crew who are still learning that role.</p>
    <div class="grid" style="margin-top:16px">
        @forelse($roles as $role)
            @php($qualification = $existingQualifications->get($role->id))
            <div class="grid" style="grid-template-columns:minmax(180px,1fr) repeat(3,minmax(140px,1fr));align-items:end;border-bottom:1px solid var(--line);padding-bottom:14px">
                <label>{{ $role->name }}<select name="qualifications[{{ $role->id }}][status]"><option value="">Not assigned</option>@foreach($qualificationStatuses as $status)<option value="{{ $status->value }}" @selected(old("qualifications.{$role->id}.status", $qualification?->status?->value) === $status->value)>{{ ucfirst($status->value) }}</option>@endforeach</select></label>
                <label>Effective from<input type="date" name="qualifications[{{ $role->id }}][effective_from]" value="{{ old("qualifications.{$role->id}.effective_from", $qualification?->effective_from?->toDateString()) }}"></label>
                <label>Effective until<input type="date" name="qualifications[{{ $role->id }}][effective_until]" value="{{ old("qualifications.{$role->id}.effective_until", $qualification?->effective_until?->toDateString()) }}"></label>
                <label>Notes<input name="qualifications[{{ $role->id }}][notes]" value="{{ old("qualifications.{$role->id}.notes", $qualification?->notes) }}" maxlength="1000"></label>
            </div>
        @empty
            <div class="muted">Add roles from the Crew page first.</div>
        @endforelse
    </div>
</div>

<div style="margin-top:16px"><button type="submit">{{ $submitLabel }}</button> <a class="button secondary" href="{{ route('admin.crew.index') }}">Cancel</a></div>
