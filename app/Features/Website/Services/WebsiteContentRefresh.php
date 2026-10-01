<?php

namespace App\Features\Website\Services;

use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Website\Models\WebsitePage;
use Illuminate\Support\Str;

class WebsiteContentRefresh
{
    public function content(): array
    {
        return json_decode(file_get_contents(resource_path('content/website-2026-refresh.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function apply(): void
    {
        $original = WebsiteDefaults::pages();
        foreach ($this->content()['pages'] as $slug => $content) {
            $page = WebsitePage::firstOrNew(['slug' => $slug]);
            if (! $page->exists) {
                $page->fill(['draft' => $content, 'published' => $content, 'published_at' => now()])->save();

                continue;
            }
            // Replace only untouched starter copy. Keep independent drafts and hidden pages.
            if (isset($original[$slug]) && $page->draft == $original[$slug]) {
                $page->draft = $content;
            }
            if (isset($original[$slug]) && $page->published == $original[$slug]) {
                $page->published = $content;
                $page->published_at = now();
            }
            $page->save();
        }
        $this->refreshHelpReferences('Media Help', 'My Messages');
        foreach ($this->content()['help'] as $content) {
            CustomerHelpArticle::firstOrCreate(['slug' => 'refresh-'.Str::slug($content['title'])], [
                ...$content, 'uuid' => (string) Str::uuid(), 'is_published' => true,
            ]);
        }
    }

    public function revert(): void
    {
        $this->refreshHelpReferences('My Messages', 'Media Help');
        $original = WebsiteDefaults::pages();
        foreach ($this->content()['pages'] as $slug => $content) {
            $page = WebsitePage::where('slug', $slug)->first();
            if (! $page) {
                continue;
            }
            if (! isset($original[$slug])) {
                if ($page->draft == $content && $page->published == $content) {
                    $page->delete();
                }

                continue;
            }
            if ($page->draft == $content) {
                $page->draft = $original[$slug];
            }
            if ($page->published == $content) {
                $page->published = $original[$slug];
            }
            $page->save();
        }
        foreach ($this->content()['help'] as $content) {
            $article = CustomerHelpArticle::where('slug', 'refresh-'.Str::slug($content['title']))->first();
            if ($article && $article->draft === null && collect($article->getAttributes())->only(array_keys($content))->all() == $content) {
                $article->delete();
            }
        }
    }

    private function refreshHelpReferences(string $from, string $to): void
    {
        $article = CustomerHelpArticle::where('slug', 'what-if-my-download-period-has-ended')->first();
        $old = "Open My orders and choose Request reactivation beside the expired media. Explain what you need, then follow the request in {$from}.\n\nAn approved extension affects only the selected photo or video entitlement and displays its new end date.";
        if ($article && $article->body === $old) {
            $article->update(['body' => str_replace($from, $to, $old)]);
        }
    }
}
