<?php

namespace App\Features\Reporting\Services;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerConcertPhotoSelection;
use App\Features\Customers\Models\CustomerEngagementEvent;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Models\GuestGalleryAccess;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Services\OrderFinancials;
use App\Features\Orders\Support\OrderStatus;
use App\Features\Programmes\Models\ProgrammePhotoGallery;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Studios\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GalleryEngagementReport
{
    public function events(array $filters): array
    {
        /** @var array<string, array<string, mixed>> $aggregates */
        $aggregates = [];
        $includeEmptyGalleries = empty($filters['date_from'])
            && empty($filters['date_to'])
            && empty($filters['customer']);

        if ($includeEmptyGalleries) {
            $this->galleryKeys($filters['kind'] ?? null)->each(
                fn (array $event) => $this->aggregate($aggregates, $event['kind'], $event['uuid'])
            );
        }

        $activity = CustomerEngagementEvent::query()->with('user')
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('event_kind', $kind));
        $this->applyDates($activity, 'occurred_at', $filters);
        $this->applyUserSearch($activity, $filters['customer'] ?? null);
        $activity->get()->each(function (CustomerEngagementEvent $event) use (&$aggregates): void {
            if (! $event->user) {
                return;
            }
            $aggregate = &$this->aggregate($aggregates, $event->event_kind, $event->event_uuid);
            $identity = $this->identity($event->user->email, $event->user_id);
            $aggregate['visitors'][$identity] = true;
            $this->latest($aggregate, $event->occurred_at);
        });

        $competitionSelections = CustomerMediaSelection::query()->with(['user', 'event'])
            ->where(fn (Builder $query) => $query->where('is_favourite', true)->orWhere('in_cart', true));
        $this->applyDates($competitionSelections, 'updated_at', $filters);
        $this->applyUserSearch($competitionSelections, $filters['customer'] ?? null);
        $competitionSelections->get()->each(function (CustomerMediaSelection $selection) use (&$aggregates, $filters): void {
            if (! $selection->user || ! $selection->event || (($filters['kind'] ?? null) && $filters['kind'] !== 'competition')) {
                return;
            }
            $aggregate = &$this->aggregate($aggregates, 'competition', $selection->event->uuid);
            $this->selection($aggregate, $this->identity($selection->user->email, $selection->user_id), $selection);
        });

        $concertSelections = CustomerConcertPhotoSelection::query()->with(['user', 'concert'])
            ->where(fn (Builder $query) => $query->where('is_favourite', true)->orWhere('in_cart', true));
        $this->applyDates($concertSelections, 'updated_at', $filters);
        $this->applyUserSearch($concertSelections, $filters['customer'] ?? null);
        $concertSelections->get()->each(function (CustomerConcertPhotoSelection $selection) use (&$aggregates, $filters): void {
            if (! $selection->user || ! $selection->concert || (($filters['kind'] ?? null) && $filters['kind'] !== 'concert')) {
                return;
            }
            $aggregate = &$this->aggregate($aggregates, 'concert', $selection->concert->uuid);
            $this->selection($aggregate, $this->identity($selection->user->email, $selection->user_id), $selection);
        });

        $guestAccesses = GuestGalleryAccess::query()->with(['selections', 'event:id,uuid', 'concert:id,uuid'])
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('gallery_type', $kind));
        $this->applyAccessDates($guestAccesses, $filters);
        $guestAccesses->get()->each(function (GuestGalleryAccess $access) use (&$aggregates, $filters): void {
            if (! $this->matchesGuest($access, $filters['customer'] ?? null)) {
                return;
            }
            $uuid = $this->guestEventUuid($access);
            if (! $uuid) {
                return;
            }
            $aggregate = &$this->aggregate($aggregates, $access->gallery_type, $uuid);
            $identity = $this->identity($access->email);
            $aggregate['visitors'][$identity] = true;
            foreach ($access->selections as $selection) {
                $this->selection($aggregate, $identity, $selection);
            }
            $this->latest($aggregate, $access->last_used_at ?? $access->created_at);
        });

        foreach ($this->orderAllocations($filters, $filters['kind'] ?? null) as $allocation) {
            $aggregate = &$this->aggregate($aggregates, $allocation['kind'], $allocation['uuid']);
            $identity = $this->identity($allocation['order']->customer_email, $allocation['order']->user_id);
            $aggregate['visitors'][$identity] = true;
            if ($allocation['complimentary']) {
                $aggregate['complimentary'][$identity] = true;
                $aggregate['complimentary_items'] += $allocation['items'];
            } else {
                $aggregate['customers_with_orders'][$identity] = true;
                $aggregate['orders'][$allocation['order']->id] = true;
                $aggregate['sales'] += $allocation['amount'];
            }
            $this->latest($aggregate, $allocation['order']->paid_at);
        }

        $descriptors = $this->descriptors(collect($aggregates)->values());
        $events = collect($aggregates)->map(function (array $aggregate, string $key) use ($descriptors): ?array {
            $descriptor = $descriptors->get($key);
            if (! $descriptor) {
                return null;
            }

            return $descriptor + $this->statistics($aggregate);
        })->filter()->sort(function (array $left, array $right): int {
            $leftDate = $left['last_activity'] ?? $left['event_date'];
            $rightDate = $right['last_activity'] ?? $right['event_date'];

            return ($rightDate?->timestamp ?? 0) <=> ($leftDate?->timestamp ?? 0)
                ?: strcasecmp($left['name'], $right['name']);
        })->values();

        return [
            'rows' => $this->hierarchicalRows($events, $aggregates),
            'summary' => [
                'visitors' => $events->sum('visitors'),
                'with_favourites' => $events->sum('with_favourites'),
                'with_cart' => $events->sum('with_cart'),
                'with_orders' => $events->sum('with_orders'),
                'without_orders' => $events->sum('without_orders'),
            ],
        ];
    }

    public function activity(string $kind, string $uuid, array $filters, bool $paginate = true): array
    {
        abort_unless(in_array($kind, ['competition', 'concert'], true), 404);
        $descriptor = $this->descriptor($kind, $uuid);
        abort_unless($descriptor, 404);

        return $this->activityFor($kind, collect([$descriptor['model']]), $descriptor, $filters, $paginate);
    }

    public function studioActivity(Studio $studio, array $filters, bool $paginate = true): array
    {
        $concerts = Concert::query()->with(['studio', 'bookingItem.schedulingEvent'])
            ->where('studio_id', $studio->id)
            ->get();
        abort_if($concerts->isEmpty(), 404);

        $descriptor = [
            'kind' => 'concert',
            'uuid' => $studio->uuid,
            'name' => $studio->name,
            'event_date' => null,
            'organiser' => null,
            'gallery_url' => route('admin.studios.edit', $studio),
            'open_label' => 'Open studio',
            'activity_url' => route('admin.media-sales.studios.activity', $studio),
            'export_url' => route('admin.media-sales.studios.activity.export', $studio),
            'scope_label' => 'Concert studio galleries',
            'context_label' => 'All '.$concerts->count().' concert '.str('event')->plural($concerts->count()),
            'model' => $studio,
        ];

        return $this->activityFor('concert', $concerts, $descriptor, $filters, $paginate);
    }

    private function activityFor(
        string $kind,
        Collection $models,
        array $descriptor,
        array $filters,
        bool $paginate,
    ): array {
        $uuids = $models->pluck('uuid');
        $ids = $models->pluck('id');

        /** @var array<string, array<string, mixed>> $visitors */
        $visitors = [];

        $activity = CustomerEngagementEvent::query()->with('user')
            ->where('event_kind', $kind)->whereIn('event_uuid', $uuids);
        $this->applyDates($activity, 'occurred_at', $filters);
        $activity->get()->each(function (CustomerEngagementEvent $event) use (&$visitors): void {
            if (! $event->user) {
                return;
            }
            $visitor = &$this->visitor($visitors, $event->user->email, $event->user);
            $visitor['views'] += $event->action === 'viewed' ? 1 : 0;
            $this->visitorActivity($visitor, $event->occurred_at);
        });

        if ($kind === 'competition') {
            $selections = CustomerMediaSelection::query()->with('user')->whereIn('scheduling_event_id', $ids)
                ->where(fn (Builder $query) => $query->where('is_favourite', true)->orWhere('in_cart', true));
        } else {
            $selections = CustomerConcertPhotoSelection::query()->with('user')->whereIn('concert_id', $ids)
                ->where(fn (Builder $query) => $query->where('is_favourite', true)->orWhere('in_cart', true));
        }
        $this->applyDates($selections, 'updated_at', $filters);
        $selections->get()->each(function ($selection) use (&$visitors): void {
            if (! $selection->user) {
                return;
            }
            $visitor = &$this->visitor($visitors, $selection->user->email, $selection->user);
            $visitor['favourites'] += $selection->is_favourite ? 1 : 0;
            $visitor['cart_items'] += $selection->in_cart ? 1 : 0;
            $this->visitorActivity($visitor, $selection->updated_at);
        });

        $guestAccesses = GuestGalleryAccess::query()->with('selections')->where('gallery_type', $kind)
            ->when($kind === 'competition', fn (Builder $query) => $query->whereIn('scheduling_event_id', $ids))
            ->when($kind === 'concert', fn (Builder $query) => $query->whereIn('concert_id', $ids));
        $this->applyAccessDates($guestAccesses, $filters);
        $guestAccesses->get()->each(function (GuestGalleryAccess $access) use (&$visitors): void {
            $visitor = &$this->visitor($visitors, $access->email);
            $visitor['access'] = $visitor['user'] ? 'Account' : 'Guest';
            foreach ($access->selections as $selection) {
                $visitor['favourites'] += $selection->is_favourite ? 1 : 0;
                $visitor['cart_items'] += $selection->in_cart ? 1 : 0;
                $this->visitorActivity($visitor, $selection->updated_at);
            }
            $this->visitorActivity($visitor, $access->last_used_at ?? $access->created_at);
        });

        foreach ($this->orderAllocations($filters, $kind, onlyUuids: $uuids) as $allocation) {
            /** @var Order $order */
            $order = $allocation['order'];
            $visitor = &$this->visitor($visitors, $order->customer_email, $order->user);
            if ($allocation['complimentary']) {
                $visitor['complimentary'] += $allocation['items'];
            } else {
                $visitor['orders'][$order->id] ??= ['model' => $order, 'amount' => 0];
                $visitor['orders'][$order->id]['amount'] += $allocation['amount'];
                $visitor['paid_total'] += $allocation['amount'];
            }
            $this->visitorActivity($visitor, $order->paid_at);
        }

        $rows = collect($visitors)->map(function (array $visitor): array {
            $visitor['order_count'] = count($visitor['orders']);
            $visitor['orders'] = collect($visitor['orders'])->values();

            return $visitor;
        })->filter(fn (array $visitor): bool => $this->matchesVisitor($visitor, $filters['q'] ?? null))
            ->filter(fn (array $visitor): bool => match ($filters['activity'] ?? 'all') {
                'favourites' => $visitor['favourites'] > 0,
                'cart' => $visitor['cart_items'] > 0,
                'ordered' => $visitor['order_count'] > 0,
                'no_order' => $visitor['order_count'] === 0,
                'complimentary' => $visitor['complimentary'] > 0,
                default => true,
            })->sortByDesc(fn (array $visitor) => $visitor['last_activity']?->timestamp ?? 0)->values();

        $summary = [
            'visitors' => $rows->count(),
            'with_favourites' => $rows->where('favourites', '>', 0)->count(),
            'with_cart' => $rows->where('cart_items', '>', 0)->count(),
            'with_orders' => $rows->where('order_count', '>', 0)->count(),
            'without_orders' => $rows->where('order_count', 0)->count(),
            'orders' => $rows->sum('order_count'),
            'sales' => $rows->sum('paid_total'),
        ];

        return [
            'event' => $descriptor,
            'summary' => $summary,
            'visitors' => $paginate ? $this->paginate($rows, (int) ($filters['per_page'] ?? 50)) : $rows,
            'filters' => $filters,
        ];
    }

    private function galleryKeys(?string $kind): Collection
    {
        $keys = collect();
        if (! $kind || $kind === 'competition') {
            $eventIds = ProgrammePhotoGallery::query()->distinct()->pluck('scheduling_event_id');
            SchedulingEvent::query()->whereIn('id', $eventIds)->where('event_type', 'competition')
                ->get(['uuid'])->each(fn (SchedulingEvent $event) => $keys->push(['kind' => 'competition', 'uuid' => $event->uuid]));
        }
        if (! $kind || $kind === 'concert') {
            Concert::query()->whereHas('mediaCollections', fn (Builder $query) => $query->where('media_type', 'photo'))
                ->get(['uuid'])->each(fn (Concert $concert) => $keys->push(['kind' => 'concert', 'uuid' => $concert->uuid]));
        }

        return $keys;
    }

    /** @return array<string, mixed> */
    private function &aggregate(array &$aggregates, string $kind, string $uuid): array
    {
        $key = $this->key($kind, $uuid);
        $aggregates[$key] ??= $this->blankAggregate($kind, $uuid);

        return $aggregates[$key];
    }

    private function blankAggregate(string $kind, string $uuid): array
    {
        return [
            'kind' => $kind, 'uuid' => $uuid, 'visitors' => [], 'with_favourites' => [],
            'with_cart' => [], 'customers_with_orders' => [], 'orders' => [], 'complimentary' => [],
            'complimentary_items' => 0, 'sales' => 0, 'last_activity' => null,
        ];
    }

    private function statistics(array $aggregate): array
    {
        return [
            'visitors' => count($aggregate['visitors']),
            'with_favourites' => count($aggregate['with_favourites']),
            'with_cart' => count($aggregate['with_cart']),
            'with_orders' => count($aggregate['customers_with_orders']),
            'without_orders' => count(array_diff_key($aggregate['visitors'], $aggregate['customers_with_orders'])),
            'orders' => count($aggregate['orders']),
            'sales' => $aggregate['sales'],
            'with_complimentary' => count($aggregate['complimentary']),
            'complimentary_items' => $aggregate['complimentary_items'],
            'last_activity' => $aggregate['last_activity'],
        ];
    }

    private function hierarchicalRows(Collection $events, array $aggregates): Collection
    {
        $groups = collect();

        $events->where('kind', 'competition')->each(function (array $event) use ($groups): void {
            $groups->push([
                'row' => $event + ['row_type' => 'competition'],
                'children' => collect(),
            ]);
        });

        $events->where('kind', 'concert')->groupBy(fn (array $event): string => $event['studio_uuid'] ?: 'concert:'.$event['uuid'])
            ->each(function (Collection $concerts, string $studioKey) use ($groups, $aggregates): void {
                /** @var Concert $firstConcert */
                $firstConcert = $concerts->first()['model'];
                $studio = $firstConcert->studio;
                if (! $studio || str_starts_with($studioKey, 'concert:')) {
                    $groups->push([
                        'row' => $concerts->first() + ['row_type' => 'concert'],
                        'children' => collect(),
                    ]);

                    return;
                }

                $studioAggregate = $this->blankAggregate('concert', $studio->uuid);
                $concerts->each(function (array $concert) use (&$studioAggregate, $aggregates): void {
                    $aggregate = $aggregates[$this->key('concert', $concert['uuid'])] ?? null;
                    if ($aggregate) {
                        $this->mergeAggregate($studioAggregate, $aggregate);
                    }
                });
                $children = $concerts->map(fn (array $concert): array => $concert + [
                    'row_type' => 'concert_event',
                    'parent_uuid' => $studio->uuid,
                ])->values();
                $groups->push([
                    'row' => [
                        'kind' => 'concert',
                        'uuid' => $studio->uuid,
                        'name' => $studio->name,
                        'event_date' => null,
                        'organiser' => null,
                        'gallery_url' => route('admin.studios.edit', $studio),
                        'open_label' => 'Open studio',
                        'activity_url' => route('admin.media-sales.studios.activity', $studio),
                        'export_url' => route('admin.media-sales.studios.activity.export', $studio),
                        'scope_label' => 'Concert studio galleries',
                        'context_label' => 'All '.$children->count().' concert '.str('event')->plural($children->count()),
                        'model' => $studio,
                        'row_type' => 'studio',
                        'event_count' => $children->count(),
                    ] + $this->statistics($studioAggregate),
                    'children' => $children,
                ]);
            });

        return $groups->sort(function (array $left, array $right): int {
            $leftDate = $left['row']['last_activity'] ?? $left['row']['event_date'];
            $rightDate = $right['row']['last_activity'] ?? $right['row']['event_date'];

            return ($rightDate?->timestamp ?? 0) <=> ($leftDate?->timestamp ?? 0)
                ?: strcasecmp($left['row']['name'], $right['row']['name']);
        })->flatMap(fn (array $group): Collection => collect([$group['row']])->concat($group['children']))->values();
    }

    private function mergeAggregate(array &$target, array $source): void
    {
        foreach (['visitors', 'with_favourites', 'with_cart', 'customers_with_orders', 'orders', 'complimentary'] as $key) {
            $target[$key] = $target[$key] + $source[$key];
        }
        $target['complimentary_items'] += $source['complimentary_items'];
        $target['sales'] += $source['sales'];
        $this->latest($target, $source['last_activity']);
    }

    private function selection(array &$aggregate, string $identity, $selection): void
    {
        $aggregate['visitors'][$identity] = true;
        if ($selection->is_favourite) {
            $aggregate['with_favourites'][$identity] = true;
        }
        if ($selection->in_cart) {
            $aggregate['with_cart'][$identity] = true;
        }
        $this->latest($aggregate, $selection->updated_at);
    }

    private function latest(array &$aggregate, ?Carbon $at): void
    {
        if ($at && (! $aggregate['last_activity'] || $at->greaterThan($aggregate['last_activity']))) {
            $aggregate['last_activity'] = $at;
        }
    }

    private function guestEventUuid(GuestGalleryAccess $access): ?string
    {
        if ($access->gallery_type === 'competition') {
            return $access->event?->uuid;
        }

        return $access->concert?->uuid;
    }

    private function descriptors(Collection $aggregates): Collection
    {
        $competitionUuids = $aggregates->where('kind', 'competition')->pluck('uuid')->unique();
        $concertUuids = $aggregates->where('kind', 'concert')->pluck('uuid')->unique();
        $events = SchedulingEvent::query()->with('competitionContact')->whereIn('uuid', $competitionUuids)->get()->keyBy('uuid');
        $concerts = Concert::query()->with(['studio', 'bookingItem.schedulingEvent'])->whereIn('uuid', $concertUuids)->get()->keyBy('uuid');

        return $aggregates->mapWithKeys(function (array $aggregate) use ($events, $concerts): array {
            $model = $aggregate['kind'] === 'competition' ? $events->get($aggregate['uuid']) : $concerts->get($aggregate['uuid']);

            return $model ? [$this->key($aggregate['kind'], $aggregate['uuid']) => $this->descriptorData($aggregate['kind'], $model)] : [];
        });
    }

    private function descriptor(string $kind, string $uuid): ?array
    {
        $model = $kind === 'competition'
            ? SchedulingEvent::query()->with('competitionContact')->where('uuid', $uuid)->where('event_type', 'competition')->first()
            : Concert::query()->with(['studio', 'bookingItem.schedulingEvent'])->where('uuid', $uuid)->first();

        return $model ? $this->descriptorData($kind, $model) : null;
    }

    private function descriptorData(string $kind, SchedulingEvent|Concert $model): array
    {
        $schedulingEvent = $kind === 'competition' ? $model : $model->bookingItem?->schedulingEvent;
        $galleryUrl = $kind === 'competition'
            ? route('admin.competition-gallery.show', $model)
            : ($schedulingEvent ? route('admin.concert-photo-gallery.show', $schedulingEvent) : route('admin.concert-photo-gallery.events'));

        return [
            'kind' => $kind,
            'uuid' => $model->uuid,
            'name' => $model->name,
            'event_date' => $model->event_date,
            'organiser' => $kind === 'competition' ? $model->competitionContact?->name : $model->studio?->name,
            'studio_uuid' => $kind === 'concert' ? $model->studio?->uuid : null,
            'gallery_url' => $galleryUrl,
            'open_label' => 'Open gallery',
            'activity_url' => route('admin.media-sales.events.activity', ['kind' => $kind, 'eventUuid' => $model->uuid]),
            'export_url' => route('admin.media-sales.events.activity.export', ['kind' => $kind, 'eventUuid' => $model->uuid]),
            'scope_label' => ucfirst($kind).' gallery',
            'context_label' => ($kind === 'competition' ? $model->competitionContact?->name : $model->studio?->name) ?: ucfirst($kind),
            'model' => $model,
        ];
    }

    private function orderAllocations(
        array $filters,
        ?string $onlyKind = null,
        ?string $onlyUuid = null,
        ?Collection $onlyUuids = null,
    ): Collection {
        $orders = Order::query()->with(['user', 'items.mediaCollection.concert'])
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Fulfilled->value, OrderStatus::Refunded->value])
            ->whereNotNull('paid_at');
        $this->applyDates($orders, 'paid_at', $filters);
        $this->applyOrderSearch($orders, $filters['customer'] ?? $filters['q'] ?? null);

        return $orders->get()->flatMap(function (Order $order) use ($onlyKind, $onlyUuid, $onlyUuids): Collection {
            /** @var array<string, array<string, mixed>> $groups */
            $groups = [];
            foreach ($order->items as $item) {
                $reference = $this->itemEvent($order, $item);
                if (! $reference) {
                    continue;
                }
                [$kind, $uuid] = $reference;
                if (($onlyKind && $kind !== $onlyKind)
                    || ($onlyUuid && $uuid !== $onlyUuid)
                    || ($onlyUuids && ! $onlyUuids->contains($uuid))) {
                    continue;
                }
                $key = $this->key($kind, $uuid);
                $groups[$key] ??= ['kind' => $kind, 'uuid' => $uuid, 'amount' => 0, 'items' => 0, 'complimentary' => true];
                $groups[$key]['amount'] += (int) data_get($item->metadata, 'net_after_account_credit', $item->total_price_amount);
                $groups[$key]['items'] += max(1, (int) $item->quantity);
                $groups[$key]['complimentary'] = $groups[$key]['complimentary']
                    && (data_get($item->metadata, 'complimentary', false)
                        || data_get($order->metadata, 'source') === 'complimentary_media_grant');
            }

            if ($groups === [] && data_get($order->metadata, 'kind') === 'competition') {
                $uuid = data_get($order->metadata, 'source_uuid');
                if ($uuid && (! $onlyKind || $onlyKind === 'competition')
                    && (! $onlyUuid || $onlyUuid === $uuid)
                    && (! $onlyUuids || $onlyUuids->contains($uuid))) {
                    $groups[$this->key('competition', $uuid)] = [
                        'kind' => 'competition', 'uuid' => $uuid, 'amount' => $order->total_amount,
                        'items' => max(1, $order->items->sum('quantity')), 'complimentary' => false,
                    ];
                }
            }

            return collect($groups)->map(fn (array $group): array => $group + ['order' => $order]);
        })->values();
    }

    private function itemEvent(Order $order, $item): ?array
    {
        return app(OrderFinancials::class)->event($order, $item);
    }

    /** @return array<string, mixed> */
    private function &visitor(array &$visitors, string $email, ?User $user = null): array
    {
        $key = $this->identity($email, $user?->id);
        $visitors[$key] ??= [
            'key' => $key, 'user' => $user, 'name' => $user?->name ?: 'Guest visitor',
            'email' => $email, 'access' => $user ? 'Account' : 'Guest', 'first_activity' => null,
            'last_activity' => null, 'views' => 0, 'favourites' => 0, 'cart_items' => 0,
            'complimentary' => 0, 'orders' => [], 'paid_total' => 0,
        ];
        if ($user && ! $visitors[$key]['user']) {
            $visitors[$key]['user'] = $user;
            $visitors[$key]['name'] = $user->name;
            $visitors[$key]['access'] = 'Account';
        }

        return $visitors[$key];
    }

    private function visitorActivity(array &$visitor, ?Carbon $at): void
    {
        if (! $at) {
            return;
        }
        if (! $visitor['first_activity'] || $at->lessThan($visitor['first_activity'])) {
            $visitor['first_activity'] = $at;
        }
        if (! $visitor['last_activity'] || $at->greaterThan($visitor['last_activity'])) {
            $visitor['last_activity'] = $at;
        }
    }

    private function matchesVisitor(array $visitor, ?string $search): bool
    {
        if (! $search) {
            return true;
        }
        $term = mb_strtolower(trim($search));
        $haystack = mb_strtolower($visitor['name'].' '.$visitor['email'].' '.($visitor['user']?->id ?? ''));
        if (str_contains($haystack, $term)) {
            return true;
        }

        return $visitor['orders']->contains(fn (array $order) => str_contains(
            mb_strtolower('order '.$order['model']->displayNumber()),
            $term,
        ));
    }

    private function identity(?string $email, ?int $userId = null): string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== '' ? 'email:'.hash('sha256', $email) : 'user:'.($userId ?? 'unknown');
    }

    private function matchesGuest(GuestGalleryAccess $access, ?string $search): bool
    {
        return ! $search || str_contains(mb_strtolower($access->email), mb_strtolower(trim($search)));
    }

    private function applyUserSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }
        $term = trim($search);
        $query->whereHas('user', function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $query->orWhere($query->qualifyColumn('id'), (int) $term);
                }
            });
        });
    }

    private function applyOrderSearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }
        $term = trim($search);
        $orderId = Order::idFromDisplayNumber($term);
        $query->where(function (Builder $query) use ($term, $orderId): void {
            $query->where('customer_name', 'like', '%'.$term.'%')->orWhere('customer_email', 'like', '%'.$term.'%');
            if ($orderId) {
                $query->orWhere($query->qualifyColumn('id'), $orderId);
            }
        });
    }

    private function applyDates(Builder $query, string $column, array $filters): void
    {
        $query->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where($column, '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where($column, '<=', $date.' 23:59:59'));
    }

    private function applyAccessDates(Builder $query, array $filters): void
    {
        if ($filters['date_from'] ?? null) {
            $date = $filters['date_from'].' 00:00:00';
            $query->where(fn (Builder $query) => $query->where('last_used_at', '>=', $date)
                ->orWhere(fn (Builder $query) => $query->whereNull('last_used_at')->where('created_at', '>=', $date)));
        }
        if ($filters['date_to'] ?? null) {
            $date = $filters['date_to'].' 23:59:59';
            $query->where(fn (Builder $query) => $query->where('last_used_at', '<=', $date)
                ->orWhere(fn (Builder $query) => $query->whereNull('last_used_at')->where('created_at', '<=', $date)));
        }
    }

    private function paginate(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function key(string $kind, string $uuid): string
    {
        return $kind.'|'.$uuid;
    }
}
