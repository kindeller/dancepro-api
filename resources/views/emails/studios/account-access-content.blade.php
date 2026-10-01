<p style="margin:0 0 8px;color:#087fab;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase">Your DancePro account</p>
<h1 style="margin:0 0 24px;color:#101820;font-size:28px;line-height:1.2">{{ $active ? 'Your Studio Hub is ready' : 'Your Studio Hub invitation' }}</h1>
<p>{{ $firstName ? 'Hi '.$firstName.',' : 'Hi,' }}</p>
@if($active)
    <p>You now have <strong>{{ $roleLabel }}</strong> access to <strong>{{ $studioName }}</strong> in DancePro. This has been added to your existing account, so you're ready to get started.</p>
@else
    <p>DancePro has added you as a contact for <strong>{{ $studioName }}</strong> and invited you to its Studio Hub with the role of <strong>{{ $roleLabel }}</strong>. This will give you one place to manage the studio's events and communications with us.</p>
    <p>{{ $existingAccount ? 'Sign in to your existing DancePro account and verify your email address to activate this access.' : 'Create a DancePro account and verify your email address to activate this access.' }} Use <strong>{{ $email }}</strong>.</p>
@endif
<h2 style="margin:24px 0 12px;color:#101820;font-size:21px">What you can do</h2>
<ul style="margin:0 0 20px;padding-left:24px">
    <li>View concert bookings, event timelines and outstanding actions.</li>
    <li>Confirm event information and upload programmes, videos and slideshows.</li>
    <li>Read studio messages and view, download or pay event invoices.</li>
    @if($canRelease)<li>Review concert videos and release them to families when they're ready.</li>@endif
</ul>
@if($active)<p>Sign in with <strong>{{ $email }}</strong>, then choose <strong>Studio Hub</strong> from your account menu. You can also use the button below to open {{ $studioName }} directly.</p>@endif
<p style="margin:24px 0"><a href="{{ $actionUrl }}" style="display:inline-block;border-radius:7px;background:#0aa0db;color:#fff;font-weight:700;padding:13px 19px;text-decoration:none">{{ $actionLabel }}</a></p>
<p style="padding:16px;background:#eaf8fd;border-left:4px solid #0aa0db;font-size:14px"><strong>Was this added incorrectly?</strong><br>If you don't recognise this studio or shouldn't have this access, reply to this email or contact <a href="mailto:{{ $supportEmail }}" style="color:#087fab">{{ $supportEmail }}</a> so we can correct it.</p>
<p style="font-size:12px;color:#667782">Button not working? Open this link:<br><a href="{{ $actionUrl }}" style="color:#087fab;word-break:break-all">{{ $actionUrl }}</a></p>
