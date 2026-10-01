<?php

namespace App\Features\Website\Services;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Media\Models\MediaCommerceSetting;
use Illuminate\Support\Facades\DB;

class CustomerFeatureGuides
{
    public const PREVIOUS_INTRO = "Need help with your media? Contact DancePro and include your event name and order number.\n\nPurchased media and download links will appear in My Media when delivery is enabled.";

    public const INTRO = 'Find your galleries, manage your account and make the most of your photos and videos. Browse a topic or search below.';

    public function articles(): array
    {
        return json_decode(file_get_contents(resource_path('content/website-customer-guides.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function apply(bool $revert = false): void
    {
        DB::transaction(function () use ($revert): void {
            foreach ($this->articles() as $content) {
                if (! $revert) {
                    // Preserve edited drafts and deliberately unpublished articles.
                    CustomerHelpArticle::firstOrCreate(['slug' => $content['slug']], [...$content, 'is_published' => true]);

                    continue;
                }
                $article = CustomerHelpArticle::where('slug', $content['slug'])->first();
                if ($article && $article->draft === null && $article->is_published
                    && $article->only(array_keys($content)) == $content) {
                    $article->delete();
                }
            }

            $setting = MediaCommerceSetting::query()->lockForUpdate()->first();
            [$from, $to] = $revert ? [self::INTRO, self::PREVIOUS_INTRO] : [self::PREVIOUS_INTRO, self::INTRO];
            if ($setting && ($setting->settings['help_content'] ?? null) === $from) {
                $setting->update(['settings' => [...$setting->settings, 'help_content' => $to]]);
            }
        });
    }
}
