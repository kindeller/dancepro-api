<p>Hi {{ $invitation->firstName }},</p>
<h1>You’re invited to My Family</h1>
<p>{{ $invitation->managerName }} has invited you to join their family in DancePro.</p>
<p>Once you accept, their added studios and events will appear in your account automatically, including later additions. Release and expiry rules still apply.</p>
<p>Your account permissions after you accept:</p>
<ul>
    <li><strong>Access galleries, favourites &amp; cart:</strong> included. Gallery access codes and release rules still apply.</li>
    <li><strong>View &amp; download purchased media:</strong> {{ $invitation->viewMedia ? 'access eligible family purchases' : 'no access to other members’ purchased media' }}.</li>
    <li><strong>Make purchases:</strong> {{ $invitation->purchase ? 'enabled, using your own payment details' : 'no purchase permission' }}.</li>
</ul>
<p>Accepting automatically shares your eligible existing and future purchases with family members who have media access. Your orders and payment details remain private. Normal release and download expiry rules still apply.</p>
<p><a href="{{ $invitation->invitationUrl }}" style="display:inline-block;background:#00a3d7;color:#ffffff;padding:14px 24px;text-decoration:none;font-weight:bold">Review family invitation</a></p>
<p>Sign in, or create an account with the email address that received this invitation, and verify it. Review the permissions before accepting. This invitation expires in 14 days.</p>
<p>The account manager can change your family permissions or remove you. You can leave in My Profile → My Family; shared access ends, but your own purchases stay yours. You can belong to one family at a time.</p>
<p>If you weren’t expecting this, you can ignore this invitation; no access is granted until you accept. If the link expires, ask the account manager to send a new invitation.</p>
