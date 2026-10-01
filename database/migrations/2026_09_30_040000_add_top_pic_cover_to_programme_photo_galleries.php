<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programme_photo_galleries', function (Blueprint $table): void {
            $table->foreignId('top_pic_cover_photo_id')->nullable()->constrained('programme_photo_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programme_photo_galleries', fn (Blueprint $table) => $table->dropConstrainedForeignId('top_pic_cover_photo_id'));
    }
};
