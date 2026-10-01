<?php

use App\Features\Website\Services\WebsiteDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->json('draft');
            $t->json('published')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('website_biographies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crew_profile_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('draft');
            $t->json('published')->nullable();
            $t->longText('reviewed_source')->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('scheduling_events', fn (Blueprint $t) => $t->boolean('website_visible')->default(false)->index());
        Schema::table('customer_help_articles', fn (Blueprint $t) => $t->json('draft')->nullable());
        foreach (WebsiteDefaults::pages() as $slug => $content) {
            DB::table('website_pages')->insert(['slug' => $slug, 'draft' => json_encode($content), 'published' => json_encode($content), 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (WebsiteDefaults::help() as [$category,$title,$summary,$body]) {
            DB::table('customer_help_articles')->insert(['uuid' => (string) Str::uuid(), 'slug' => 'website-'.Str::slug($title), 'category' => $category, 'title' => $title, 'summary' => $summary, 'body' => $body, 'keywords' => null, 'sort_order' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('customer_help_articles')->where('slug', 'like', 'website-%')->delete();
        Schema::table('customer_help_articles', fn (Blueprint $t) => $t->dropColumn('draft'));
        Schema::table('scheduling_events', fn (Blueprint $t) => $t->dropColumn('website_visible'));
        Schema::dropIfExists('website_biographies');
        Schema::dropIfExists('website_pages');
    }
};
