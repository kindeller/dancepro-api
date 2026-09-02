<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_profiles', function (Blueprint $table) {
            $table->text('date_of_birth')->nullable()->after('commencement_date');
            $table->string('address_line_1')->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('suburb')->nullable()->after('address_line_2');
            $table->string('state', 50)->nullable()->after('suburb');
            $table->string('postcode', 20)->nullable()->after('state');
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->text('abn')->nullable();
            $table->text('bank_account_name')->nullable();
            $table->text('bank_bsb')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->text('super_fund_name')->nullable();
            $table->text('super_member_number')->nullable();
            $table->text('dietary_requirements')->nullable();
            $table->text('medical_information')->nullable();
            $table->text('drivers_licence_number')->nullable();
            $table->text('working_with_children_number')->nullable();
            $table->date('working_with_children_expiry')->nullable();
            $table->text('first_aid_details')->nullable();
            $table->date('first_aid_expiry')->nullable();
            $table->text('owned_equipment')->nullable();
            $table->string('usual_travel_area')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->text('internal_notes')->nullable();
        });

        Schema::create('crew_vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('crew_profile_id')->constrained('crew_profiles')->cascadeOnDelete();
            $table->string('make');
            $table->string('model');
            $table->string('registration');
            $table->string('colour')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['crew_profile_id', 'registration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_vehicles');

        Schema::table('crew_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'address_line_1', 'address_line_2', 'suburb', 'state', 'postcode',
                'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
                'abn', 'bank_account_name', 'bank_bsb', 'bank_account_number', 'super_fund_name',
                'super_member_number', 'dietary_requirements', 'medical_information',
                'drivers_licence_number', 'working_with_children_number', 'working_with_children_expiry',
                'first_aid_details', 'first_aid_expiry', 'owned_equipment', 'usual_travel_area',
                'profile_photo_path', 'internal_notes',
            ]);
        });
    }
};
