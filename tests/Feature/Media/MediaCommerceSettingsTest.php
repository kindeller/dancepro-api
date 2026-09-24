<?php

namespace Tests\Feature\Media;

use App\Features\Concerts\Models\Concert;
use App\Features\Customers\Models\CustomerMediaSelection;
use App\Features\Customers\Services\CustomerCompetitionCart;
use App\Features\Media\Models\MediaCollection;
use App\Features\Media\Models\MediaDiscountCode;
use App\Features\Media\Models\MediaPriceList;
use App\Features\Media\Models\MediaWatermark;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Media\Services\MediaPriceLists;
use App\Features\Payments\Services\OrderDiscounts;
use App\Features\Programmes\Models\Programme;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Programmes\Services\CompetitionCustomerPreview;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MediaCommerceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_change_prices_and_discount_tiers_used_by_new_quotes(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.media-settings.index'))->assertOk()->assertSee('<h2>Competition</h2>', false);
        $competitionPrices = MediaPriceList::where('gallery_type', 'competition')->where('is_default', true)->firstOrFail();
        $this->patch(route('admin.media-price-lists.update', $competitionPrices), [
            'name' => 'Competition default', 'gallery_type' => 'competition', 'competition_photo' => 12,
            'competition_routine_photos' => 45, 'competition_routine_video' => 20,
            'competition_routine_package' => 50, 'gst_rate' => 10,
            'gst_applicable' => ['competition_photo', 'competition_routine_photos', 'competition_routine_video', 'competition_routine_package'],
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1200, $competitionPrices->fresh()->price('competition_photo'));

        $customer = User::factory()->customer()->create();
        $event = SchedulingEvent::create(['name' => 'Cart event', 'event_type' => 'competition', 'event_date' => '2026-09-04']);
        $item = (string) Str::uuid();
        CustomerMediaSelection::create(['user_id' => $customer->id, 'scheduling_event_id' => $event->id,
            'selection_key' => 'routine_photos:'.$item, 'product' => 'routine_photos', 'item_uuid' => $item, 'in_cart' => true]);
        $gallery = $this->mock(CompetitionCustomerPreview::class);
        $gallery->shouldReceive('browse')->andReturn(['selected' => ['label' => 'Routine']]);
        $quote = app(CustomerCompetitionCart::class)->forEvent($customer, $event);
        $this->assertSame(4500, $quote['subtotal']);
        $this->assertSame(4500, $quote['total']);

        $this->post(route('admin.media-settings.update'), [
            'section' => 'discounts',
            'bulk_1_threshold' => 40, 'bulk_1_percent' => 10,
            'bulk_2_threshold' => 100, 'bulk_2_percent' => 15,
            'bulk_3_threshold' => 150, 'bulk_3_percent' => 20,
            'bulk_4_threshold' => 200, 'bulk_4_percent' => 25,
            'upsell_enabled' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $quote = app(CustomerCompetitionCart::class)->forEvent($customer, $event);
        $this->assertSame(10, $quote['discount_percent']);
        $this->assertSame(4050, $quote['total']);
    }

    public function test_gallery_settings_are_split_into_page_tabs(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        SchedulingEvent::create(['name' => 'Competition gallery', 'event_type' => 'competition', 'event_date' => now()]);
        Concert::factory()->create(['name' => 'Concert gallery']);

        $this->get(route('admin.media-settings.index'))
            ->assertOk()
            ->assertSee('Products &amp; pricing', false)
            ->assertSee('<h2>Competition</h2>', false)
            ->assertSee('<h2>Concert</h2>', false)
            ->assertSee('+ Create price list')
            ->assertSee('Assigned to 1 gallery')
            ->assertDontSee('Discount codes');

        $this->get(route('admin.media-settings.index', ['tab' => 'discounts']))
            ->assertOk()
            ->assertSee('aria-selected="true">Discounts &amp; promotions</a>', false)
            ->assertSee('Bulk discounts &amp; upselling', false)
            ->assertSee('Add discount code')
            ->assertSee('Created discount codes')
            ->assertSee('name="discount_value"', false)
            ->assertSee('Uses per customer account')
            ->assertSee('value="'.today()->format('Y-m-d\TH:i').'"', false)
            ->assertSee('value="'.today()->addDays(30)->endOfDay()->format('Y-m-d\TH:i').'"', false)
            ->assertDontSee('Competition price lists');

        $this->get(route('admin.media-settings.index', ['tab' => 'unknown']))
            ->assertOk()
            ->assertSee('<h2>Competition</h2>', false);
    }

    public function test_custom_price_lists_can_override_competition_routines_and_concert_albums(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get(route('admin.media-settings.index'))->assertOk();

        $this->post(route('admin.media-price-lists.store'), [
            'name' => 'Premium competition', 'gallery_type' => 'competition',
            'competition_photo' => 12, 'competition_routine_photos' => 48,
            'competition_routine_video' => 20, 'competition_routine_package' => 55, 'gst_rate' => 10,
            'gst_applicable' => ['competition_photo'],
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $competitionList = MediaPriceList::where('name', 'Premium competition')->firstOrFail();
        $this->assertTrue($competitionList->productIncludesGst('competition_photo'));
        $this->assertFalse($competitionList->productIncludesGst('competition_routine_photos'));
        $this->assertSame(1000, $competitionList->gst_rate_basis_points);
        $event = SchedulingEvent::create(['name' => 'Premium event', 'event_type' => 'competition', 'event_date' => now()]);
        $programme = Programme::create(['scheduling_event_id' => $event->id]);
        $routine = ProgrammeNode::create(['programme_id' => $programme->id, 'kind' => 'item',
            'data' => ['title' => 'Solo'], 'sort_order' => 1, 'is_active' => true]);

        $this->post(route('admin.media-price-lists.assign'), [
            'target' => 'competition:'.$event->id, 'media_price_list_id' => $competitionList->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4800, app(MediaPriceLists::class)
            ->competitionPrice($event->fresh(), 'competition_routine_photos', $routine->uuid));

        $routineList = MediaPriceList::create(['name' => 'Solo special', 'gallery_type' => 'competition',
            'prices' => ['competition_photo' => 900, 'competition_routine_photos' => 3300,
                'competition_routine_video' => 1500, 'competition_routine_package' => 4000],
            'is_active' => true]);
        $this->post(route('admin.media-price-lists.assign'), [
            'target' => 'routine:'.$routine->id, 'media_price_list_id' => $routineList->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3300, app(MediaPriceLists::class)
            ->competitionPrice($event->fresh(), 'competition_routine_photos', $routine->uuid));
        $this->get(route('admin.media-settings.index'))
            ->assertOk()
            ->assertSeeInOrder(['Premium competition', 'Assigned to 1 gallery'])
            ->assertSeeInOrder(['Solo special', 'Assigned to 0 galleries', '1 album/routine override']);

        $this->post(route('admin.media-price-lists.store'), [
            'name' => 'Concert special', 'gallery_type' => 'concert', 'concert_photo' => 14,
            'gst_rate' => 10, 'gst_applicable' => ['concert_photo'], 'is_active' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $concertList = MediaPriceList::where('name', 'Concert special')->firstOrFail();
        $concert = Concert::factory()->create();
        $album = MediaCollection::factory()->create(['concert_id' => $concert->id]);
        $this->post(route('admin.media-price-lists.assign'), [
            'target' => 'collection:'.$album->id, 'media_price_list_id' => $concertList->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1400, app(MediaPriceLists::class)->concertPrice($concert, $album->fresh()));
    }

    public function test_codes_are_drafts_and_help_text_is_customer_visible(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->post(route('admin.media-settings.codes.store'), ['code' => 'spring10', 'discount_type' => 'percent', 'discount_value' => 10,
            'enabled' => 1, 'max_redemptions' => 20, 'max_redemptions_per_customer' => 1])->assertRedirect();
        $this->assertDatabaseHas('media_discount_codes', ['code' => 'SPRING10', 'enabled' => true,
            'max_redemptions' => 20, 'max_redemptions_per_customer' => 1]);
        $code = MediaDiscountCode::firstOrFail();
        $this->patch(route('admin.media-settings.codes.update', $code), ['code' => 'spring15', 'discount_type' => 'percent', 'discount_value' => 15])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_discount_codes', ['code' => 'SPRING15', 'percent' => 15, 'enabled' => false]);
        $this->post(route('admin.media-settings.codes.store'), ['code' => 'once40', 'discount_type' => 'fixed',
            'discount_value' => 40, 'enabled' => 1, 'ends_at' => now()->endOfMonth()->format('Y-m-d H:i:s'),
            'max_redemptions' => 1, 'max_redemptions_per_customer' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('media_discount_codes', ['code' => 'ONCE40', 'discount_type' => 'fixed',
            'amount' => 4000, 'percent' => null, 'enabled' => true, 'max_redemptions' => 1]);
        $this->get(route('admin.media-settings.index', ['tab' => 'discounts']))
            ->assertOk()
            ->assertSeeInOrder(['ONCE40', '$40.00 off', '0 used', '1 per account', 'Active'])
            ->assertSeeInOrder(['SPRING15', '15% off', 'Inactive']);
        $this->post(route('admin.media-settings.update'), ['section' => 'help', 'help_content' => 'Contact us with your order number.'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', 'Help saved.');
        $this->assertDatabaseCount('media_commerce_settings', 1);
        $this->assertSame('Contact us with your order number.', app(MediaCommerceSettings::class)->get('help_content'));
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.media.help'))
            ->assertOk()->assertSee('Contact us with your order number.');

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('admin.media-settings.index', ['tab' => 'help']))
            ->assertOk()->assertSee('Help Centre insights')->assertSee('Searches with no answer')->assertSee('Article feedback');
    }

    public function test_inactive_future_and_expired_codes_cannot_be_quoted(): void
    {
        $customer = User::factory()->customer()->create();
        $codes = [
            MediaDiscountCode::create(['code' => 'INACTIVE', 'percent' => 10, 'enabled' => false]),
            MediaDiscountCode::create(['code' => 'FUTURE10', 'percent' => 10, 'enabled' => true, 'starts_at' => now()->addDay()]),
            MediaDiscountCode::create(['code' => 'EXPIRED10', 'percent' => 10, 'enabled' => true, 'ends_at' => now()->subDay()]),
        ];

        $this->assertSame(['Inactive', 'Scheduled', 'Expired'], array_map(
            fn (MediaDiscountCode $code): string => $code->statusLabel(),
            $codes,
        ));

        foreach ($codes as $code) {
            try {
                app(OrderDiscounts::class)->quote($code->code, $customer, 1000);
                $this->fail("{$code->code} was accepted.");
            } catch (ValidationException $exception) {
                $this->assertSame('Enter a valid active discount code.', $exception->errors()['discount_code'][0]);
            }
        }
    }

    public function test_watermarks_use_compact_grouped_library_rows_without_legacy_controls(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('admin.media-watermarks.store'), [
            'name' => 'Competition preview',
            'gallery_type' => 'competition',
            'mode' => 'text',
            'text' => 'DANCEPRO PREVIEW',
            'is_default' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $watermark = MediaWatermark::query()->firstOrFail();

        $this->get(route('admin.media-settings.index', ['tab' => 'watermarks']))
            ->assertOk()
            ->assertSee('Watermark library')
            ->assertSee('+ Create watermark')
            ->assertSee('Competition preview')
            ->assertSee('0 direct assignments')
            ->assertSee('Default')
            ->assertDontSee('Legacy watermark defaults')
            ->assertDontSee('value="proof"', false);
        $this->get(route('admin.media-watermarks.preview', $watermark))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->post(route('admin.media-watermarks.update', $watermark), ['action' => 'archive'])
            ->assertSessionHasErrors('watermark');
        $this->assertTrue($watermark->fresh()->is_active);

        $this->post(route('admin.media-watermarks.store'), [
            'name' => 'Competition replacement',
            'gallery_type' => 'competition',
            'mode' => 'text',
            'text' => 'DANCEPRO',
            'is_default' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.media-watermarks.update', $watermark), ['action' => 'archive'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($watermark->fresh()->is_active);
        $this->get(route('admin.media-settings.index', ['tab' => 'watermarks']))
            ->assertOk()->assertSee('Archived watermarks')->assertSee('1 archived');
    }

    public function test_a_four_mb_png_can_be_added_to_the_watermark_library(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->staff()->create());

        $this->post(route('admin.media-watermarks.store'), [
            'name' => 'Large PNG watermark',
            'gallery_type' => 'concert',
            'mode' => 'image',
            'artwork' => UploadedFile::fake()->image('watermark.png', 4000, 4000)->size(4096),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $watermark = MediaWatermark::query()->where('name', 'Large PNG watermark')->firstOrFail();
        Storage::disk('local')->assertExists($watermark->storage_key);
        $dimensions = getimagesize(Storage::disk('local')->path($watermark->storage_key));
        $this->assertSame(2000, max($dimensions[0], $dimensions[1]));
        $this->assertSame($dimensions[0], $dimensions[1]);
    }
}
