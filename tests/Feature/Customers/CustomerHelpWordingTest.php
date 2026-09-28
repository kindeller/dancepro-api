<?php

namespace Tests\Feature\Customers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerHelpWordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_help_explains_current_access_and_multi_event_carts(): void
    {
        $articles = DB::table('customer_help_articles')->get()->keyBy('slug');

        $gettingStarted = $articles->get('how-do-i-find-my-media');
        $this->assertStringContainsString('event media access code', $gettingStarted->body);
        $this->assertStringContainsString('Complimentary media includes', $gettingStarted->body);
        $this->assertStringNotContainsString('provisional', $gettingStarted->body);

        // Existing links keep working even when an article title changes.
        $access = $articles->get('why-do-i-need-a-gallery-login-code');
        $this->assertSame('Why do I need a gallery access code?', $access->title);
        $shopping = $articles->get('what-is-the-difference-between-favourites-and-the-cart');
        $this->assertStringContainsString('unselected event carts remain saved', $shopping->body);
    }

    public function test_refresh_and_rollback_preserve_customised_articles(): void
    {
        $migration = require database_path('migrations/2026_09_26_000001_refresh_default_customer_help_wording.php');
        $slug = 'how-do-i-find-my-media';
        $original = DB::table('customer_help_articles')->where('slug', $slug)->first();

        $migration->down();
        $this->assertStringContainsString('provisional', DB::table('customer_help_articles')->where('slug', $slug)->value('body'));
        DB::table('customer_help_articles')->where('slug', $slug)->update(['body' => 'Our edited instructions.']);
        $migration->up();
        $this->assertSame('Our edited instructions.', DB::table('customer_help_articles')->where('slug', $slug)->value('body'));

        $shoppingSlug = 'what-is-the-difference-between-favourites-and-the-cart';
        DB::table('customer_help_articles')->where('slug', $shoppingSlug)->update(['title' => 'Our shopping guide']);
        $migration->down();
        $this->assertSame('Our shopping guide', DB::table('customer_help_articles')->where('slug', $shoppingSlug)->value('title'));
        $this->assertSame($original->uuid, DB::table('customer_help_articles')->where('slug', $slug)->value('uuid'));
    }
}
