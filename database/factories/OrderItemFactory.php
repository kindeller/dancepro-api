<?php

namespace Database\Factories;

use App\Features\Media\Models\MediaAsset;
use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'snapshot_storage_disk' => 'local',
            'snapshot_storage_key' => 'local-development/orders/'.fake()->uuid().'.jpg',
            'snapshot_filename' => 'performance-photo.jpg',
            'snapshot_display_name' => 'Performance photo',
            'item_type' => 'media',
            'quantity' => 1,
            'unit_price_amount' => 1500,
            'total_price_amount' => 1500,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
