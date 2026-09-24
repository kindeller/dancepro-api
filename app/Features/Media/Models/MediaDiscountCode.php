<?php

namespace App\Features\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaDiscountCode extends Model
{
    protected $fillable = ['code', 'discount_type', 'percent', 'amount', 'starts_at', 'ends_at', 'enabled', 'max_redemptions', 'max_redemptions_per_customer'];

    public function redemptions(): HasMany
    {
        return $this->hasMany(MediaDiscountRedemption::class);
    }

    public function statusLabel(): string
    {
        if (! $this->enabled) {
            return 'Inactive';
        }
        if ($this->starts_at?->isFuture()) {
            return 'Scheduled';
        }
        if ($this->ends_at?->isPast()) {
            return 'Expired';
        }
        $redemptions = $this->redemptions_count ?? $this->redemptions()->count();
        if ($this->max_redemptions !== null && $redemptions >= $this->max_redemptions) {
            return 'Used up';
        }

        return 'Active';
    }

    public function discountLabel(): string
    {
        return $this->discount_type === 'fixed'
            ? '$'.number_format((int) $this->amount / 100, 2).' off'
            : $this->percent.'% off';
    }

    protected function casts(): array
    {
        return ['percent' => 'integer', 'amount' => 'integer', 'enabled' => 'boolean', 'max_redemptions' => 'integer',
            'max_redemptions_per_customer' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
