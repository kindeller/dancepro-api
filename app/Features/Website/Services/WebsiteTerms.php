<?php

namespace App\Features\Website\Services;

use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Website\Models\WebsitePage;

class WebsiteTerms
{
    public const TABS = [
        'overview' => ['slug' => 'terms', 'label' => 'Overview'],
        'purchases' => ['slug' => 'terms-purchases', 'label' => 'Purchases & refunds'],
        'discounts' => ['slug' => 'terms-discounts', 'label' => 'Discounts & credits'],
        'downloads' => ['slug' => 'terms-downloads', 'label' => 'Downloads & access'],
        'family' => ['slug' => 'terms-family', 'label' => 'Family sharing'],
        'licences' => ['slug' => 'terms-licences', 'label' => 'Media licences'],
        'bookings' => ['slug' => 'terms-bookings', 'label' => 'Bookings & events'],
        'privacy' => ['slug' => 'privacy', 'label' => 'Privacy'],
    ];

    public function __construct(private readonly WebsiteContent $content, private readonly MediaCommerceSettings $settings) {}

    public function page(string $tab = 'overview', bool $preview = false): array
    {
        abort_unless(isset(self::TABS[$tab]), 404);
        $slugs = WebsitePage::whereNotNull('published')->pluck('slug');
        $tabs = collect(self::TABS)->filter(fn (array $item) => $slugs->contains($item['slug']));
        $tiers = collect(range(1, 4))->map(fn (int $tier) => [
            'threshold' => (int) $this->settings->get("bulk_{$tier}_threshold"),
            'percent' => (int) $this->settings->get("bulk_{$tier}_percent"),
        ])->filter(fn (array $tier) => $tier['percent'] > 0)->values();

        return $this->content->page(self::TABS[$tab]['slug'], $preview)
            + ['termsTab' => $tab, 'termsTabs' => $tabs, 'bulkTiers' => $tiers];
    }

    public function tabForSlug(string $slug): ?string
    {
        $tab = collect(self::TABS)->search(fn (array $tab) => $tab['slug'] === $slug);

        return $tab === false ? null : $tab;
    }
}
