<?php

use App\Features\Website\Services\CustomerFeatureGuides;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CustomerFeatureGuides::class)->apply();
    }

    public function down(): void
    {
        app(CustomerFeatureGuides::class)->apply(revert: true);
    }
};
