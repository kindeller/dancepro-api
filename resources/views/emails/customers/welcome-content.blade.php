<p style="margin:0 0 8px;color:#087fab;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase">Your DancePro account</p>
<h1>Welcome to DancePro</h1>
<p>Hi {{ $firstName }},</p>
<p>Your email is verified and your account is ready. Here’s how to set it up and make the most of your DancePro photos and videos.</p>
@foreach($sections as $section)
    <h2>{{ $section['title'] }}</h2>
    <p>{{ $section['body'] }}</p>
    @if($section['url'])<p><a href="{{ $section['url'] }}" style="color:#087fab;font-weight:700">{{ $section['label'] }} →</a></p>@endif
@endforeach
@if($apps)
    <h2>DancePro on your phone</h2>
    <p>Download the app and sign in with the same DancePro account.</p>
    @foreach($apps as $label => $url)<p><a href="{{ $url }}">{{ $label }}</a></p>@endforeach
@endif
<p>Keep your password and access codes private. If you didn’t create or verify this account, please <a href="{{ $helpUrl }}">contact DancePro through Help</a>.</p>
<p style="color:#667782;font-size:14px">A copy of this welcome is saved in My Messages → Notifications so you can return to these instructions later.</p>
