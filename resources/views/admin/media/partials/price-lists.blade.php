@php($productGroups = \App\Features\Media\Services\MediaPriceLists::PRODUCTS)

<div class="settings-panel">
    <section class="card card-pad price-list-section price-list-intro">
        <h2>Price lists</h2>
        <p>A competition or concert automatically uses its default price list. An album or routine override takes priority, followed by its gallery or event override, then the automatic default.</p>
        <p class="muted">Price changes apply to new cart quotes and order drafts. Paid orders keep the prices and GST captured when they were purchased.</p>
    </section>

    @foreach(['competition' => 'Competition', 'concert' => 'Concert'] as $type => $heading)
        @php($typeLists = $priceLists->where('gallery_type', $type))
        @php($defaultList = $typeLists->firstWhere('is_default', true) ?? $typeLists->first())
        <section class="price-list-section price-list-group">
            <details class="create-price-list">
                <summary class="price-list-heading">
                    <div><h2>{{ $heading }}</h2><p class="muted">{{ $type === 'competition' ? 'Competition galleries, routines and media albums.' : 'Concert galleries and photo albums.' }}</p></div>
                    <span class="button create-price-list-button">+ Create price list</span>
                </summary>
                <div class="card card-pad create-price-list-form">
                    <h3>Create {{ strtolower($heading) }} price list</h3>
                    <form method="post" action="{{ route('admin.media-price-lists.store', ['tab' => 'pricing']) }}" class="grid">@csrf
                        <input type="hidden" name="gallery_type" value="{{ $type }}">
                        <label>Price list name<input name="name" maxlength="120" placeholder="{{ $type === 'competition' ? 'Special competition pricing' : 'Studio concert pricing' }}" required></label>
                        <label>GST rate · %<input type="number" name="gst_rate" min="0" max="100" step="0.01" value="{{ number_format($defaultList->gst_rate_basis_points / 100, 2, '.', '') }}" required></label>
                        <p class="muted">Tick GST for each taxable product. The included GST component is calculated from its final discounted amount.</p>
                        @foreach($productGroups[$type] as $key => $label)
                            <div class="price-product-row">
                                <label>{{ $label }} · $<input type="number" name="{{ $key }}" min="0.01" max="100000" step="0.01" value="{{ number_format($defaultList->price($key) / 100, 2, '.', '') }}" required></label>
                                <label class="gst-toggle"><input type="checkbox" name="gst_applicable[]" value="{{ $key }}" @checked($defaultList->productIncludesGst($key))> GST</label>
                            </div>
                        @endforeach
                        @if($type === 'competition')<p class="muted">The package must cost more than its video component.</p>@endif
                        <label class="price-list-checkbox"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                        <div><button>Create price list</button></div>
                    </form>
                </div>
            </details>

            <div class="price-list-rows">
                @foreach($typeLists as $priceList)
                    <details class="card price-list-row">
                        <summary class="price-list-summary">
                            <span class="price-list-summary-name">{{ $priceList->name }}</span>
                            <span class="price-list-summary-badges">
                                @if($priceList->is_default)<span class="price-list-badge">Automatic default</span>@elseif(!$priceList->is_active)<span class="price-list-badge inactive">Inactive</span>@endif
                            </span>
                            <span class="price-list-assignment-count">Assigned to {{ $priceList->assigned_galleries_count }} {{ \Illuminate\Support\Str::plural('gallery', $priceList->assigned_galleries_count) }}@if($priceList->assigned_overrides_count) · {{ $priceList->assigned_overrides_count }} {{ \Illuminate\Support\Str::plural('album/routine override', $priceList->assigned_overrides_count) }}@endif</span>
                            <span class="price-list-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="price-list-row-form">
                            <form method="post" action="{{ route('admin.media-price-lists.update', ['priceList' => $priceList, 'tab' => 'pricing']) }}" class="grid">@csrf @method('PATCH')
                                <input type="hidden" name="gallery_type" value="{{ $type }}">
                                <label>Price list name<input name="name" value="{{ $priceList->name }}" maxlength="120" required></label>
                                <label>GST rate · %<input type="number" name="gst_rate" min="0" max="100" step="0.01" value="{{ number_format($priceList->gst_rate_basis_points / 100, 2, '.', '') }}" required></label>
                                <p class="muted">Tick GST for each taxable product. The included GST component is calculated from its final discounted amount.</p>
                                @foreach($productGroups[$type] as $key => $label)
                                    <div class="price-product-row">
                                        <label>{{ $label }} · $<input type="number" name="{{ $key }}" min="0.01" max="100000" step="0.01" value="{{ number_format($priceList->price($key) / 100, 2, '.', '') }}" required></label>
                                        <label class="gst-toggle"><input type="checkbox" name="gst_applicable[]" value="{{ $key }}" @checked($priceList->productIncludesGst($key))> GST</label>
                                    </div>
                                @endforeach
                                @if($type === 'competition')<p class="muted">The package must cost more than its video component.</p>@endif
                                <div class="price-list-options">
                                    @if($priceList->is_default)
                                        <input type="hidden" name="is_active" value="1"><span class="muted">The automatic default remains active.</span>
                                    @else
                                        <label class="price-list-checkbox"><input type="checkbox" name="is_active" value="1" @checked($priceList->is_active)> Active</label>
                                        <label class="price-list-checkbox"><input type="checkbox" name="is_default" value="1"> Make automatic default</label>
                                    @endif
                                </div>
                                <div><button>Save price list</button></div>
                            </form>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="price-list-section price-list-assignments">
        <div class="price-list-heading"><div><h2>Gallery and album assignments</h2><p class="muted">Leave an assignment on Automatic default unless this event, gallery, routine or album needs different pricing.</p></div></div>
        <div class="grid two-col">
            <article class="card card-pad">
                <h3>Competition assignment</h3>
                <form method="post" action="{{ route('admin.media-price-lists.assign', ['tab' => 'pricing']) }}" class="grid">@csrf
                    <label>Competition gallery or routine<select name="target" required>
                        <option value="">Choose a gallery or routine</option>
                        <optgroup label="Competition galleries">
                            @foreach($competitionPriceTargets as $event)<option value="competition:{{ $event->id }}">{{ $event->name }}{{ $event->event_date ? ' · '.$event->event_date->format('j M Y') : '' }} · {{ $event->priceList?->name ?? 'Automatic default' }}</option>@endforeach
                        </optgroup>
                        <optgroup label="Competition routines">
                            @foreach($routinePriceTargets as $routine)<option value="routine:{{ $routine->id }}">{{ $routine->programme?->event?->name ?? 'Competition' }} · {{ $routine->data['title'] ?? $routine->data['name'] ?? $routine->data['label'] ?? 'Routine' }} · {{ $routine->priceList?->name ?? 'Automatic default' }}</option>@endforeach
                        </optgroup>
                        <optgroup label="Competition media albums">
                            @foreach($collectionPriceTargets->whereNotNull('competition_id') as $album)<option value="collection:{{ $album->id }}">{{ $album->name }} · {{ $album->priceList?->name ?? 'Automatic default' }}</option>@endforeach
                        </optgroup>
                    </select></label>
                    <label>Price list<select name="media_price_list_id"><option value="">Automatic default</option>@foreach($priceLists->where('gallery_type', 'competition')->where('is_active', true) as $priceList)<option value="{{ $priceList->id }}">{{ $priceList->name }}</option>@endforeach</select></label>
                    <div><button>Save competition assignment</button></div>
                </form>
            </article>
            <article class="card card-pad">
                <h3>Concert assignment</h3>
                <form method="post" action="{{ route('admin.media-price-lists.assign', ['tab' => 'pricing']) }}" class="grid">@csrf
                    <label>Concert gallery or album<select name="target" required>
                        <option value="">Choose a gallery or album</option>
                        <optgroup label="Concert galleries">
                            @foreach($concertPriceTargets as $concert)<option value="concert:{{ $concert->id }}">{{ $concert->name }}{{ $concert->event_date ? ' · '.$concert->event_date->format('j M Y') : '' }} · {{ $concert->priceList?->name ?? 'Automatic default' }}</option>@endforeach
                        </optgroup>
                        <optgroup label="Concert photo albums">
                            @foreach($collectionPriceTargets->whereNotNull('concert_id') as $album)<option value="collection:{{ $album->id }}">{{ $album->concert?->name ?? 'Concert' }} · {{ $album->name }} · {{ $album->priceList?->name ?? 'Automatic default' }}</option>@endforeach
                        </optgroup>
                    </select></label>
                    <label>Price list<select name="media_price_list_id"><option value="">Automatic default</option>@foreach($priceLists->where('gallery_type', 'concert')->where('is_active', true) as $priceList)<option value="{{ $priceList->id }}">{{ $priceList->name }}</option>@endforeach</select></label>
                    <div><button>Save concert assignment</button></div>
                </form>
            </article>
        </div>
    </section>
</div>
