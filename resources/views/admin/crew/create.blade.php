@extends('layouts.admin', ['title' => 'Invite crew member', 'heading' => 'Invite crew member', 'subheading' => 'Add their name and email. They will securely set their password and complete their own profile.'])

@section('content')
    @include('admin.crew-management._tabs')
    <form method="POST" action="{{ route('admin.crew.store') }}">
        @csrf
        <div class="card card-pad">
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
