@extends('layouts.admin', ['title' => 'Booking notification', 'heading' => $booking->studio_name, 'subheading' => '1.1 Booking & services'])
@section('content')
@include('admin.tracking._task-bar', ['bar' => $trackerBar])
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" style="color:#b42331">{{ $errors->first() }}</p>@endif
<section class="card card-pad">
    <div class="toolbar"><h2>{{ \App\Features\Bookings\Support\ConcertBookingStatus::from($notification->booking_status)->label() }} — studio notification</h2><a class="button secondary" href="{{ route('admin.concert-bookings.show', $booking) }}">← Back to booking</a></div>
    @if($notification->delivery_status === 'sent')
        <p role="status" style="color:#126236"><strong>Email sent</strong> · {{ $notification->sent_at->format('j M Y, g:i a') }} · {{ $notification->sender?->name ?? 'DancePro' }} · {{ $notification->recipient_email }}</p>
        <h3>{{ $notification->subject }}</h3>
        {!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($notification->body) !!}
    @elseif($notification->delivery_status === 'sending')
        <p role="status">This email is being sent. Sending again is disabled to avoid duplicates.</p>
    @elseif(!$current)
        <p role="alert">Booking details or status have changed. This draft cannot be sent. Return to the booking to prepare a current notification.</p>
        @if(in_array($booking->status->value, ['received', 'partial_approval', 'cancelled']))<form method="POST" action="{{ route('admin.concert-bookings.status-email.refresh', [$booking, $notification]) }}">@csrf<button>Prepare updated notification</button></form>@endif
    @else
        <p>The booking status is saved. Review and customise this message before sending.@if($notification->booking_status === 'received') You can return to the booking without sending if an email is not needed.@endif</p>
        @if($notification->reason)<p><strong>Recorded cancellation reason:</strong> {{ $notification->reason }}</p>@endif
        @if($notification->delivery_status === 'failed')<p role="alert">The previous send failed. You can retry below.</p>@endif
        <form method="POST" action="{{ route('admin.concert-bookings.status-email.send', [$booking, $notification]) }}" style="display:grid;gap:16px" data-booking-email>
            @csrf
            <label>To<input type="email" name="recipient_email" value="{{ old('recipient_email', $notification->recipient_email) }}" required></label>
            <x-email-send-editor :subject="$notification->subject" :body="$notification->body" subject-name="subject" body-name="body" />
            <p class="muted">DancePro branding, your signature and the account button are added automatically. The sent message is also recorded in Studio messages.</p>
            <div><button type="submit">Send studio notification</button></div>
        </form>
    @endif
</section>
@endsection
@push('scripts')<script nonce="{{ request()->attributes->get('csp_nonce') }}">document.querySelector('[data-booking-email]')?.addEventListener('submit',event=>{const button=event.target.querySelector('button[type=submit]');button.disabled=true;button.textContent='Sending…';});</script>@endpush
