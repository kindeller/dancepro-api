@extends('layouts.admin', ['title' => 'Invite crew member', 'heading' => 'Invite crew member', 'subheading' => 'Enable My Hub for an existing account or invite a new crew member.'])

@section('content')
    @include('admin.crew-management._tabs')
    <form method="POST" action="{{ route('admin.crew.store') }}" style="margin-bottom:24px">
        @csrf
        <div class="card card-pad">
            <h2>Existing DancePro account</h2>
            <p>Enable My Hub for a customer, studio contact or staff member using their existing sign-in. Their media, purchases and studio access stay with the same account.</p>
            <label>Account email address<input type="email" name="existing_email" value="{{ old('existing_email') }}" required maxlength="255"></label>
            <p class="muted">This does not grant Admin access or send an email. They can complete their crew profile in My Hub.</p>
            <button type="submit">Enable My Hub</button>
        </div>
    </form>
    <form method="POST" action="{{ route('admin.crew.store') }}">
        @csrf
        <div class="card card-pad">
            <h2>New DancePro account</h2>
            <div class="grid two-col">
                <label>Preferred name<input name="preferred_name" value="{{ old('preferred_name') }}" required maxlength="255" autofocus></label>
                <label>Email address<input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></label>
            </div>
            <label style="margin-top:18px;display:flex;flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="send_invitation" value="1" @checked(old('send_invitation', true)) style="width:auto"> Send their setup invitation now</label>
            <div class="notice" style="margin-top:18px">For existing staff, untick this box. You can add their details and historical contract signature first, then send the invitation from the Crew list later.</div>
            <div style="margin-top:18px"><button type="submit">Create crew member</button> <a class="button secondary" href="{{ route('admin.crew.index') }}">Cancel</a></div>
        </div>
    </form>
@endsection
