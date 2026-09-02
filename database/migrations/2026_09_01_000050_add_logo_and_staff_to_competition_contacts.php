<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competition_contacts', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('name');
        });

        Schema::create('competition_contact_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_contact_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->json('emails')->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['competition_contact_id', 'position']);
        });

        DB::table('competition_contacts')->orderBy('id')->each(function (object $contact): void {
            DB::table('competition_contact_staff')->insert([
                'competition_contact_id' => $contact->id,
                'name' => $contact->organiser_name,
                'role' => null,
                'emails' => json_encode([$contact->organiser_email]),
                'phone' => $contact->organiser_phone,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_contact_staff');
        Schema::table('competition_contacts', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
