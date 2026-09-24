<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_title_card_templates')->update(['separator_start_color' => '#000000']);
    }

    public function down(): void
    {
        DB::table('concert_title_card_templates')->update(['separator_start_color' => '#0AA0DB']);
    }
};
