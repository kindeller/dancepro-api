<?php

use App\Features\Orders\Models\OrderItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('snapshot_storage_disk')->nullable()->change();
            $table->string('snapshot_storage_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (OrderItem::query()
            ->whereNull('snapshot_storage_disk')->orWhereNull('snapshot_storage_key')->exists()) {
            throw new RuntimeException('Resolve pending media sources before restoring required order-item snapshots.');
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('snapshot_storage_disk')->nullable(false)->change();
            $table->string('snapshot_storage_key')->nullable(false)->change();
        });
    }
};
