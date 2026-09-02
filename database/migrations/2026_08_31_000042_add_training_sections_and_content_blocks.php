<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('training_modules', function (Blueprint $table): void {
            $table->foreignId('training_section_id')->nullable()->after('training_course_id')->constrained()->cascadeOnDelete();
            $table->json('settings')->nullable()->after('correct_option');
        });

        DB::table('training_courses')->orderBy('id')->get()->each(function (object $course): void {
            $sectionId = DB::table('training_sections')->insertGetId([
                'training_course_id' => $course->id,
                'title' => 'Course content',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('training_modules')->where('training_course_id', $course->id)->update(['training_section_id' => $sectionId]);
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('training_section_id');
            $table->dropColumn('settings');
        });
        Schema::dropIfExists('training_sections');
    }
};
