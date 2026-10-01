<?php

use App\Features\Website\Services\WebsiteContentRefresh;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(WebsiteContentRefresh::class)->apply();
    }

    public function down(): void
    {
        app(WebsiteContentRefresh::class)->revert();
    }
};
