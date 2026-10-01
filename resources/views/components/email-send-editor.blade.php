@props(['subject', 'body', 'subjectName' => 'email_subject', 'bodyName' => 'email_body', 'bulk' => false, 'personalised' => false])
@php($branding = app(\App\Features\Tracking\Services\EmailBranding::class)->for(auth()->id()))
<div data-email-send-editor style="grid-column:1/-1;min-width:0">
    <p class="muted">Click into the subject or message to customise this email only. The standard template stays unchanged. Branding, signature and the account button are added automatically.@if($personalised) Use @{{ first_name }} for each recipient’s first name; it is filled in separately for every email.@elseif($bulk) This message goes to every recipient in this send; use a general greeting.@endif</p>
    <div class="email-send-card">
        <div class="email-send-brand"><img src="{{ $branding['logoUrl'] }}" alt="{{ $branding['businessName'] }}"></div>
        <div class="email-send-content">
            <label>Subject<input name="{{ $subjectName }}" value="{{ old($subjectName, $subject) }}" maxlength="200" required data-send-subject></label>
            <div data-visual-email hidden>
                <div class="email-send-toolbar" role="toolbar" aria-label="Message formatting">
                    <button type="button" class="button secondary" data-command="formatBlock" data-value="p">Paragraph</button>
                    <button type="button" class="button secondary" data-command="formatBlock" data-value="h2">Heading</button>
                    <button type="button" class="button secondary" data-command="bold"><strong>Bold</strong></button>
                    <button type="button" class="button secondary" data-command="italic"><em>Italic</em></button>
                    <button type="button" class="button secondary" data-command="insertUnorderedList">• List</button>
                    <button type="button" class="button secondary" data-command="insertOrderedList">1. List</button>
                    <button type="button" class="button secondary" data-command="formatBlock" data-value="blockquote">Callout</button>
                    <button type="button" class="button secondary" data-command="createLink">Link</button>
                    <button type="button" class="button secondary" data-command="unlink">Remove link</button>
                    <button type="button" class="button secondary" data-command="undo">Undo</button>
                    <button type="button" class="button secondary" data-command="redo">Redo</button>
                </div>
                <div class="email-send-message" data-email-visual contenteditable="true" role="textbox" aria-label="Message for this send" aria-multiline="true" aria-required="true">{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html(old($bodyName, $body)) !!}</div>
                <p data-email-error role="alert" hidden></p>
            </div>
            <label data-email-fallback>Message<textarea name="{{ $bodyName }}" rows="14" maxlength="20000" required data-email-source>{{ old($bodyName, $body) }}</textarea></label>
            <p>{{ $branding['signature']['signOff'] }}<br><strong>{{ $branding['signature']['name'] }}</strong>@if($branding['signature']['position'])<br>{{ $branding['signature']['position'] }}@endif</p>
        </div>
    </div>
    <button type="button" class="button secondary" data-email-reset hidden>Reset to original message</button>
    <span role="status" data-email-reset-status></span>
    <template data-email-original-subject>{{ $subject }}</template>
    <template data-email-original-body>{{ $body }}</template>
    <template data-email-original-html>{!! app(\App\Features\Tracking\Services\EmailContentFormatter::class)->html($body) !!}</template>
</div>
@once
@push('styles')
<style>
.email-send-card{border:1px solid var(--line);border-radius:8px;background:#fff;overflow:hidden;margin-bottom:14px;overflow-wrap:anywhere}
.email-send-brand{padding:18px;background:#101820}.email-send-brand img{display:block;width:240px;max-width:100%;height:auto}
.email-send-content{padding:20px}.email-send-toolbar{display:flex;flex-wrap:wrap;gap:6px;margin:16px 0 0;padding:10px;border:1px solid var(--line);background:var(--soft)}
.email-send-toolbar .button{min-height:34px;padding:5px 10px;font-size:14px}.email-send-message{min-height:260px;padding:20px;border:1px solid var(--line);border-top:0;line-height:1.65;margin-bottom:20px}
.email-send-message:focus{outline:2px solid var(--brand);outline-offset:-2px}.email-send-message h2{font-size:22px;margin:20px 0 8px}.email-send-message blockquote{margin:16px 0;border-left:4px solid var(--brand);background:var(--soft);padding:10px 14px}.email-send-message ul,.email-send-message ol{padding-left:24px}.email-send-message img{max-width:100%}[data-email-error]{color:#b42331}
@media(max-width:600px){.email-send-content,.email-send-message{padding:12px}}
</style>
@endpush
@push('scripts')
@vite('resources/js/email-send-editor.js')
@endpush
@endonce
