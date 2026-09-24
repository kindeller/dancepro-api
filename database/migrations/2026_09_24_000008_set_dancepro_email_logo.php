<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_tracker_settings')->whereNull('email_logo_url')
            ->update(['email_logo_url' => '/images/brand/dancepro-logo-inverse-landscape.png']);
    }

    public function down(): void
    {
        DB::table('concert_tracker_settings')->where('email_logo_url', '/images/brand/dancepro-logo-inverse-landscape.png')
            ->update(['email_logo_url' => null]);
    }
};
