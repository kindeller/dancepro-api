<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('concert_bookings')->whereIn('status', ['pending', 'approved'])
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('concert_booking_items')->whereColumn('concert_booking_id', 'concert_bookings.id')->where('approval_status', 'approved'))
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('concert_booking_items')->whereColumn('concert_booking_id', 'concert_bookings.id')->where('approval_status', 'pending'))
            ->update(['status' => 'partial_approval']);
    }

    public function down(): void
    {
        // Preserve all event approvals while restoring the legacy booking-state vocabulary.
        DB::table('concert_bookings')->whereIn('status', ['received', 'partial_approval'])->update(['status' => 'pending']);
    }
};
