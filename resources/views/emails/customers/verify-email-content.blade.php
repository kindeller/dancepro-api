<p style="margin:0 0 8px;color:#087fab;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase">Your DancePro account</p>
<h1 style="margin:0 0 24px;color:#101820;font-size:28px;line-height:1.2">Verify your email address</h1>
<p>Hi {{ $name }},</p>
<p>Welcome to DancePro. Confirm your email address to finish setting up your DancePro account.</p>
<p style="margin:24px 0"><a href="{{ $verificationUrl }}" style="display:inline-block;border-radius:7px;background:#0aa0db;color:#fff;font-weight:700;padding:13px 19px;text-decoration:none">Verify email address</a></p>
<p style="color:#667782;font-size:14px">This link expires in {{ $expiresInMinutes }} minutes. If it expires, you can request a new one from the verification page.</p>
<p style="color:#667782;font-size:14px">If you didn't create a DancePro account, you can safely ignore this email.</p>
<div style="border-top:1px solid #dce6eb;padding-top:18px;font-size:12px;line-height:1.6;color:#667782">
    <p style="margin:0 0 6px">Button not working? Copy and paste this link into your browser:</p>
    <a href="{{ $verificationUrl }}" style="color:#087fab;word-break:break-all;overflow-wrap:anywhere">{{ $verificationUrl }}</a>
</div>
