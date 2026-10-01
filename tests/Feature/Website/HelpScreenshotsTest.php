<?php

namespace Tests\Feature\Website;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpScreenshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_help_has_captioned_real_image_assets_with_valid_dimensions(): void
    {
        $response = $this->get('/help')->assertOk()->assertSee('On your screen')->assertSee('Example screens using sample details');
        $files = [];
        foreach (config('help-screenshots') as $slug => $shots) {
            $this->assertTrue(CustomerHelpArticle::where('slug', $slug)->where('is_published', true)->exists());
            foreach ($shots as $shot) {
                $path = public_path('images/help/'.$shot['file']);
                $this->assertFileExists($path);
                [$width, $height] = getimagesize($path);
                $this->assertSame($shot['width'], $width);
                $this->assertSame($shot['height'], $height);
                $this->assertNotEmpty($shot['alt']);
                $response->assertSee(asset('images/help/'.$shot['file']), false)->assertSee(e($shot['caption']), false);
                $files[] = $shot['file'];
            }
        }
        $this->assertCount(count($files), array_unique($files));
        $this->actingAs(User::factory()->customer()->create())->get(route('customer.media.help'))
            ->assertOk()->assertSee('data-help-screenshot', false)->assertSee('id="help-image-viewer"', false);
    }

    public function test_screenshots_follow_article_publication_and_search_visibility(): void
    {
        CustomerHelpArticle::where('slug', 'guide-messages-guide')->update(['is_published' => false]);
        $this->get('/help')->assertOk()->assertDontSee('images/help/new-message.png');
        $this->get('/help?q=corrections')->assertOk()->assertSee('images/help/review-corrections.png')
            ->assertDontSee('images/help/create-account.png');
    }
}
