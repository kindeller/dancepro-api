<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table): void {
            $table->foreignId('studio_id')->nullable()->after('venue_id')->constrained()->nullOnDelete();
        });

        $studios = DB::table('studios')->whereNull('deleted_at')->get(['id', 'name'])
            ->groupBy(fn ($studio): string => mb_strtolower(trim($studio->name)));

        DB::table('scheduling_events')->where('event_type', 'concert')->whereNull('studio_id')->orderBy('id')
            ->each(function ($event) use ($studios): void {
                $matches = $studios->get(mb_strtolower(trim($event->name)), collect());
                if ($matches->count() === 1) {
                    DB::table('scheduling_events')->where('id', $event->id)->update(['studio_id' => $matches->first()->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('scheduling_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('studio_id');
        });
    }
};
