<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_price_lists', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gst_rate_basis_points')->default(1000)->after('prices');
            $table->json('taxable_products')->nullable()->after('gst_rate_basis_points');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('tax_amount')->default(0)->after('total_amount');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tax_rate_basis_points')->default(1000)->after('total_price_amount');
            $table->unsignedBigInteger('tax_amount')->default(0)->after('tax_rate_basis_points');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['tax_rate_basis_points', 'tax_amount']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tax_amount'));
        Schema::table('media_price_lists', fn (Blueprint $table) => $table->dropColumn(['gst_rate_basis_points', 'taxable_products']));
    }
};
