@props(['items'])
<nav class="gallery-breadcrumbs" aria-label="Gallery navigation">
    <ol>
        @foreach($items as $item)
            <li>
                @if(!$loop->first)<span class="gallery-breadcrumb-separator" aria-hidden="true">›</span>@endif
                @if(!$loop->last && !empty($item['url']))
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span @if($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
