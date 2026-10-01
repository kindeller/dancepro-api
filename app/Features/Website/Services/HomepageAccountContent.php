<?php

namespace App\Features\Website\Services;

use App\Features\Website\Models\WebsitePage;
use Illuminate\Support\Facades\DB;

class HomepageAccountContent
{
    public function apply(bool $revert = false): void
    {
        $previous = app(WebsiteContentRefresh::class)->content()['pages']['home'];
        $updated = json_decode(file_get_contents(resource_path('content/website-home-account.json')), true, flags: JSON_THROW_ON_ERROR);
        [$from, $to] = $revert ? [$updated, $previous] : [$previous, $updated];

        DB::transaction(function () use ($from, $to) {
            $page = WebsitePage::where('slug', 'home')->lockForUpdate()->first();
            if (! $page) {
                return;
            }

            foreach (['draft', 'published'] as $version) {
                $content = $page->$version;
                if ($content === null) {
                    continue;
                }
                // Refresh only unchanged copy; retain images, custom edits and hidden pages.
                foreach ($from as $key => $value) {
                    if (($content[$key] ?? null) == $value) {
                        $content[$key] = $to[$key];
                    }
                }
                $page->$version = $content;
            }
            $page->save();
        });
    }
}
