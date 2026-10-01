@php($screenshots = config('help-screenshots.'.$article->slug, []))
@if($screenshots)
<section class="help-visual-guide" aria-label="Screen guide for {{ $article->title }}">
    <h3>On your screen</h3>
    <p class="meta">Example screens using sample details. Tap a screenshot to enlarge it. Your events and available options may differ.</p>
    <div class="help-visual-grid">
        @foreach($screenshots as $shot)
        <figure class="help-shot">
            <figcaption><strong><span class="help-step" aria-hidden="true">{{ $loop->iteration }}</span>{{ $shot['title'] }}</strong><p>{{ $shot['caption'] }}</p></figcaption>
            <a href="{{ asset('images/help/'.$shot['file']) }}" target="_blank" rel="noopener" data-help-screenshot data-caption="{{ $shot['title'] }}" aria-label="Enlarge screenshot: {{ $shot['title'] }}">
                <img src="{{ asset('images/help/'.$shot['file']) }}" width="{{ $shot['width'] }}" height="{{ $shot['height'] }}" alt="{{ $shot['alt'] }}" loading="lazy" decoding="async">
                <span class="help-shot-enlarge">Enlarge screenshot <span aria-hidden="true">↗</span></span>
            </a>
        </figure>
        @endforeach
    </div>
</section>
@endif
