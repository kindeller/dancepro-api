<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->json('emails')->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['studio_id', 'position']);
        });

        DB::table('studios')
            ->where(function ($query): void {
                $query->whereNotNull('contact_name')
                    ->orWhereNotNull('contact_email')
                    ->orWhereNotNull('contact_phone');
            })
            ->orderBy('id')
            ->each(function (object $studio): void {
                DB::table('studio_contacts')->insert([
                    'studio_id' => $studio->id,
                    'name' => $studio->contact_name ?: 'Studio contact',
                    'role' => null,
                    'emails' => $studio->contact_email ? json_encode([$studio->contact_email]) : null,
                    'phone' => $studio->contact_phone,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_contacts');
    }
};
