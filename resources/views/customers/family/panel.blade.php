@include('customers.family._styles')
<section id="family" class="card card-body">
    @php($hasDancer = $family->members->contains('is_dancer', true))
    <div class="profile-section-heading">
        <h2>My Family</h2>
        <span class="profile-section-status {{ $hasDancer ? 'complete' : 'missing' }}" role="status">{{ $hasDancer ? 'Dancer added' : 'Dancer not added' }}</span>
    </div>
    <p>Manage your family’s dancers and account permissions. Accepted members automatically receive the account manager’s added studios and events. Eligible purchases are shared according to permissions; everyone keeps their own favourites, cart and payment details.</p>
    <details class="family-note"><summary>How family sharing works</summary><p>Only accepted accounts receive shared access. One family per account. Leaving removes inherited studio, event and purchased-media access but keeps anything you added or purchased yourself. Event release and expiry rules still apply. Studio Hub, crew and admin permissions aren’t shared. Files already downloaded cannot be recalled.</p></details>
    @if(session('family_invitation_url'))<p><a class="button secondary" href="{{ session('family_invitation_url') }}">Review your family invitation</a></p>@endif
    @foreach($family->members->sortBy(fn($member) => $member->user_id === $family->manager_user_id ? 0 : 1) as $member)
        @php($managerForm = $member->user_id === $family->manager_user_id)
        <article class="family-row">
            @if($familyManager)
                @include('customers.family.form')
                @if(!$managerForm)
                    <div class="family-actions">
                        @if($member->email && $member->status === 'pending')
                            <form method="POST" action="{{ route('customer.family.store') }}">@csrf<input type="hidden" name="action" value="invite"><input type="hidden" name="member_uuid" value="{{ $member->uuid }}"><button class="button secondary">Resend invite</button></form>
                            @if($member->status === 'pending')<form method="POST" action="{{ route('customer.family.store') }}">@csrf<input type="hidden" name="action" value="cancel"><input type="hidden" name="member_uuid" value="{{ $member->uuid }}"><button class="button secondary">Cancel invitation</button></form>@endif
                        @endif
                        <form id="family-remove-{{ $member->uuid }}" method="POST" action="{{ route('customer.family.store') }}" hidden>@csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="member_uuid" value="{{ $member->uuid }}"></form>
                        @if($member->invited_at)<p class="meta">Invited {{ $member->invited_at->format('j M Y, g:ia') }}.</p>@endif
                    </div>
                @endif
            @else
                <div class="family-read-row">
                    <div class="family-identity">@include('customers.family.identity')</div>
                    @include('customers.family.permissions', ['readOnly' => true, 'value' => fn($key, $default = null) => $default])
                </div>
            @endif
        </article>
    @endforeach
    @if($familyManager)
        @php($adding = old('action') === 'save' && !old('member_uuid'))
        <button class="button secondary family-add-toggle" type="button" data-family-add aria-controls="family-add-form" aria-expanded="{{ $adding ? 'true' : 'false' }}">+ Add family member</button>
        <div id="family-add-form" class="family-add family-row" @if(!$adding) hidden @endif>
            @include('customers.family.form', ['member' => null, 'managerForm' => false])
        </div>
    @else
        <p class="meta">{{ $family->manager->name }} manages this family. Ask them to change permissions or invite another member.</p>
        <details><summary>Leave family</summary><p>You will lose access to other members’ shared purchases. Your own account and purchases stay yours.</p><form method="POST" action="{{ route('customer.family.store') }}">@csrf<input type="hidden" name="action" value="leave"><button class="button secondary">Confirm leave family</button></form></details>
    @endif
    <dialog id="family-remove-dialog" class="family-remove-dialog" aria-labelledby="family-remove-title" aria-describedby="family-remove-description">
        <h3 id="family-remove-title">Remove family member?</h3>
        <p id="family-remove-description">Remove <strong data-family-remove-name></strong> from your family? Shared access ends immediately. Their own account and purchases stay theirs.</p>
        <div class="family-dialog-actions"><button class="button secondary" type="button" data-family-remove-cancel autofocus>Cancel</button><button class="button" type="submit" data-family-remove-confirm>Remove member</button></div>
    </dialog>
</section>
<script>
(() => {
    document.querySelectorAll('.family-form').forEach(form => {
        const email = form.querySelector('[data-family-email]');
        const search = form.querySelector('[data-family-search]');
        const update = () => {
            const accountAccess = ['can_view_media','can_purchase'].some(name => form.querySelector(`input[type=checkbox][name=${name}]`).checked);
            form.querySelector('[data-family-browse]').hidden = !accountAccess;
            const dancer = form.querySelector('input[type=checkbox][name=is_dancer]').checked;
            search.hidden = !dancer;
            if (!dancer) search.open = false;
            if (email) {
                const required = accountAccess;
                email.hidden = !required;
                const input = email.querySelector('input');
                input.required = required;
                const changed = input.value.trim().toLowerCase() !== form.dataset.originalEmail.toLowerCase()
                    || Number(form.querySelector('input[type=checkbox][name=can_view_media]').checked) !== Number(form.dataset.originalView)
                    || Number(form.querySelector('input[type=checkbox][name=can_purchase]').checked) !== Number(form.dataset.originalPurchase);
                const invite = required && form.dataset.invitationState !== 'active'
                    && (form.dataset.invitationState !== 'pending' || changed);
                const save = form.querySelector('[data-family-save]');
                save.textContent = invite ? 'Save & send invite' : save.dataset.defaultLabel;
                if (required && !input.value && email.closest('details')) email.closest('details').open = true;
            }
        };
        form.addEventListener('change', update);
        form.addEventListener('input', update);
        form.addEventListener('invalid', event => { const details = event.target.closest('details'); if (details) details.open = true; }, true);
        update();
    });
    const removeDialog = document.getElementById('family-remove-dialog');
    document.querySelectorAll('[data-family-remove]').forEach(button => button.addEventListener('click', () => {
        removeDialog.querySelector('[data-family-remove-name]').textContent = button.dataset.memberName;
        removeDialog.querySelector('[data-family-remove-confirm]').setAttribute('form', button.dataset.familyRemove);
        removeDialog.showModal();
    }));
    removeDialog.querySelector('[data-family-remove-cancel]').addEventListener('click', () => removeDialog.close());
    const add = document.querySelector('[data-family-add]');
    add?.addEventListener('click', () => {
        const panel = document.getElementById(add.getAttribute('aria-controls'));
        panel.hidden = !panel.hidden;
        add.setAttribute('aria-expanded', String(!panel.hidden));
        if (!panel.hidden) panel.querySelector('[name=first_name]').focus();
    });
})();
</script>
