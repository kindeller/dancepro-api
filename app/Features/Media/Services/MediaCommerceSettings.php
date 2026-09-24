<?php

namespace App\Features\Media\Services;

use App\Features\Media\Models\MediaCommerceSetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class MediaCommerceSettings
{
    public const DEFAULTS = [
        'competition_photo' => 1000,
        'competition_routine_photos' => 4000,
        'competition_routine_video' => 1800,
        'competition_routine_package' => 4500,
        'concert_photo' => 1000,
        'bulk_1_threshold' => 10000, 'bulk_1_percent' => 5,
        'bulk_2_threshold' => 15000, 'bulk_2_percent' => 10,
        'bulk_3_threshold' => 20000, 'bulk_3_percent' => 15,
        'bulk_4_threshold' => 25000, 'bulk_4_percent' => 20,
        'upsell_enabled' => true,
        'watermark_text' => 'DANCEPRO PREVIEW DO NOT COPY',
        'competition_watermark_path' => null,
        'receipt_email_subject' => 'Your DancePro order confirmation',
        'receipt_email_body' => 'Thank you for your order. Ready downloads are available in My Media, and pending items will appear when processing is complete.',
        'ready_email_subject' => 'Your DancePro media is ready',
        'ready_email_body' => 'Your media is ready in My Media. Sign in to view it.',
        'expiry_email_subject' => 'Your DancePro downloads expire soon',
        'expiry_email_body' => 'Sign in to My Media to download your purchase before it expires.',
        'help_content' => "Need help with your media? Contact DancePro and include your event name and order number.\n\nPurchased media and download links will appear in My Media when delivery is enabled.",
    ];

    public function all(): array
    {
        try {
            $saved = Schema::hasTable('media_commerce_settings')
                ? MediaCommerceSetting::query()->first()?->settings ?? []
                : [];
        } catch (QueryException) {
            $saved = [];
        }

        return array_replace(self::DEFAULTS, $saved);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function price(string $product): int
    {
        return (int) $this->get($product);
    }

    public function discountPercent(int $subtotal): int
    {
        $percent = 0;
        for ($tier = 1; $tier <= 4; $tier++) {
            if ($subtotal >= (int) $this->get("bulk_{$tier}_threshold")) {
                $percent = (int) $this->get("bulk_{$tier}_percent");
            }
        }

        return $percent;
    }

    public function nextDiscountThreshold(int $subtotal): ?int
    {
        $currentPercent = $this->discountPercent($subtotal);
        for ($tier = 1; $tier <= 4; $tier++) {
            $threshold = (int) $this->get("bulk_{$tier}_threshold");
            if ($subtotal < $threshold && (int) $this->get("bulk_{$tier}_percent") > $currentPercent) {
                return $threshold;
            }
        }

        return null;
    }

    public function save(array $changes): void
    {
        $record = MediaCommerceSetting::query()->first() ?? new MediaCommerceSetting;
        $record->settings = array_replace($this->all(), $changes);
        $record->save();
    }
}
