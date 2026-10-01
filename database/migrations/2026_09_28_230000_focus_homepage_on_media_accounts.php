<?php

use App\Features\Website\Services\HomepageAccountContent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(HomepageAccountContent::class)->apply();
    }

    public function down(): void
    {
        app(HomepageAccountContent::class)->apply(revert: true);
    }
};
