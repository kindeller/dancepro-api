<?php

namespace Tests\Feature\Customers;

use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Media\Support\MediaType;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerCompetitionCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_thresholds_are_inclusive_and_overlapping_photos_are_charged_once(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Cart event', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $routines = collect(range(1, 7))->map(fn () => (string) Str::uuid());
        foreach ($routines as $index => $uuid) {
            CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
                'selection_key' => 'routine_photos:'.$uuid, 'product' => 'routine_photos',
                'item_uuid' => $uuid, 'in_cart' => $index < 2]);
        }
        $gallery = $this->mock(CompetitionCustomerPreview::class);
        $gallery->shouldReceive('browse')->andReturnUsing(fn ($event, $uuid) => ['selected' => ['label' => $uuid]]);
        $cart = app(CustomerCompetitionCart::class);
        $this->assertSame(8000, $cart->forEvent($user, $event)['total']);
        foreach ([2 => [12000, 5, 11400], 3 => [16000, 10, 14400], 4 => [20000, 15, 17000], 6 => [28000, 20, 22400]] as $index => [$subtotal, $percent, $total]) {
            CustomerMediaSelection::where('user_id', $user->id)->where('item_uuid', $routines[$index])->update(['in_cart' => true]);
            if ($index === 6) {
                CustomerMediaSelection::where('user_id', $user->id)->whereIn('item_uuid', [$routines[5], $routines[6]])->update(['in_cart' => true]);
            }
            $quote = $cart->forEvent($user, $event);
            $this->assertSame($subtotal, $quote['subtotal']);
            $this->assertSame($percent, $quote['discount_percent']);
            $this->assertSame($total, $quote['total']);
        }
    }

    public function test_previous_paid_video_reduces_routine_package_quote_to_twenty_seven_dollars(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Cart event', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $item = (string) Str::uuid();
        $collection = MediaCollection::factory()->forCompetition($event->id)->create([
            'media_type' => MediaType::Video, 'metadata' => ['item_uuid' => $item],
        ]);
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'media_collection_id' => $collection->id,
            'media_asset_id' => null]);
        CustomerMediaSelection::create(['user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_package:'.$item, 'product' => 'routine_package',
            'item_uuid' => $item, 'in_cart' => true]);
        $gallery = $this->mock(CompetitionCustomerPreview::class);
        $gallery->shouldReceive('browse')->andReturn(['selected' => ['label' => 'Routine']]);

        $quote = app(CustomerCompetitionCart::class)->forEvent($user, $event);
        $this->assertSame(2700, $quote['subtotal']);
        $this->assertSame(2700, $quote['total']);
    }

    public function test_cart_and_favourite_selections_survive_a_price_list_change(): void
    {
        $user = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Repriced event', 'event_type' => 'competition', 'event_date' => now()]);
        $item = (string) Str::uuid();
        $selection = CustomerMediaSelection::create([
            'user_id' => $user->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_photos:'.$item, 'product' => 'routine_photos',
            'item_uuid' => $item, 'in_cart' => true, 'is_favourite' => true,
        ]);
        $this->assertSame(4000, app(CustomerCompetitionCart::class)->forEvent($user, $event)['subtotal']);
        $special = MediaPriceList::create([
            'name' => 'Repriced competition', 'gallery_type' => 'competition', 'is_active' => true,
            'prices' => ['competition_photo' => 1200, 'competition_routine_photos' => 5000,
                'competition_routine_video' => 2000, 'competition_routine_package' => 5800],
        ]);

        $event->update(['media_price_list_id' => $special->id]);
        $quote = app(CustomerCompetitionCart::class)->forEvent($user, $event->fresh());

        $this->assertSame(5000, $quote['subtotal']);
        $this->assertTrue($selection->fresh()->in_cart);
        $this->assertTrue($selection->fresh()->is_favourite);
    }
}
