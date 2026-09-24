<?php

namespace App\Features\Media\Models;

use App\Features\Concerts\Models\Concert;
use App\Features\Programmes\Models\ProgrammeNode;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Models\User;
use App\Shared\Models\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'name', 'gallery_type', 'prices', 'gst_rate_basis_points', 'taxable_products', 'is_default', 'is_active', 'created_by_user_id'])]
class MediaPriceList extends Model
{
    use HasPublicUuid;

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function competitionEvents(): HasMany
    {
        return $this->hasMany(SchedulingEvent::class)->where('event_type', 'competition');
    }

    public function concerts(): HasMany
    {
        return $this->hasMany(Concert::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MediaCollection::class);
    }

    public function programmeNodes(): HasMany
    {
        return $this->hasMany(ProgrammeNode::class);
    }

    public function price(string $product): ?int
    {
        $price = $this->prices[$product] ?? null;

        return $price === null ? null : (int) $price;
    }

    public function includedTax(int $amount): int
    {
        $rate = (int) $this->gst_rate_basis_points;

        return $rate === 0 ? 0 : (int) round($amount * $rate / (10000 + $rate));
    }

    public function productIncludesGst(string $product): bool
    {
        return $this->taxable_products === null || in_array($product, $this->taxable_products, true);
    }

    protected function casts(): array
    {
        return ['prices' => 'array', 'gst_rate_basis_points' => 'integer', 'taxable_products' => 'array',
            'is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
