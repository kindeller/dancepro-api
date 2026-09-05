<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_chat_messages', function (Blueprint $table): void {
            $table->text('body')->nullable()->change();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('direct_chat_messages')->whereNull('body')->update(['body' => '']);

        Schema::table('direct_chat_messages', function (Blueprint $table): void {
            $table->text('body')->nullable(false)->change();
            $table->dropColumn([
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
            ]);
        });
    }
};
