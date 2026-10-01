<?php

use App\Features\Website\Models\WebsitePage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private function pages(): array
    {
        return json_decode(file_get_contents(resource_path('content/website-terms-2026.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function up(): void
    {
        foreach ($this->pages() as $slug => $content) {
            WebsitePage::firstOrCreate(['slug' => $slug], [
                'draft' => $content, 'published' => $content, 'published_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->pages() as $slug => $content) {
            $page = WebsitePage::where('slug', $slug)->first();
            // Preserve any owner edits, unpublished drafts or deliberately hidden pages.
            if ($page && $page->draft == $content && $page->published == $content && ! $page->published_by) {
                $page->delete();
            }
        }
    }
};
