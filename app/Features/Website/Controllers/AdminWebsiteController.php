<?php

namespace App\Features\Website\Controllers;

use App\Features\Crew\Models\CrewProfile;
use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Scheduling\Models\SchedulingEvent;
use App\Features\Website\Models\WebsitePage;
use App\Features\Website\Requests\SaveWebsiteBiographyRequest;
use App\Features\Website\Requests\SaveWebsiteHelpRequest;
use App\Features\Website\Requests\SaveWebsitePageRequest;
use App\Features\Website\Requests\WebsiteEventVisibilityRequest;
use App\Features\Website\Services\WebsiteContent;
use App\Features\Website\Services\WebsiteTerms;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminWebsiteController extends Controller
{
    public function __construct(private readonly WebsiteContent $content) {}

    public function index()
    {
        return view('admin.website.pages', ['pages' => $this->content->pages()]);
    }

    public function page(WebsitePage $page)
    {
        return view('admin.website.page', ['page' => $page]);
    }

    public function preview(WebsitePage $page)
    {
        $terms = app(WebsiteTerms::class);
        $tab = $terms->tabForSlug($page->slug);

        return response()->view($tab ? 'website.terms' : 'website.page', $tab ? $terms->page($tab, true) : $this->content->page($page->slug, true))
            ->header('Cache-Control', 'private, no-store');
    }

    public function savePage(SaveWebsitePageRequest $request, WebsitePage $page)
    {
        $this->content->savePage($page, $request->validated(), $request->user());

        return back()->with('status', $this->message($request->validated('action')));
    }

    public function events(Request $request)
    {
        return view('admin.website.events', ['events' => $this->content->events(mb_substr((string) $request->query('q'), 0, 150))]);
    }

    public function event(WebsiteEventVisibilityRequest $request, SchedulingEvent $schedulingEvent)
    {
        $this->content->setEventVisibility($schedulingEvent, $request->boolean('website_visible'));

        return back()->with('status', 'Website event visibility updated.');
    }

    public function bios()
    {
        return view('admin.website.bios', ['crewProfiles' => $this->content->biographies()]);
    }

    public function bio(CrewProfile $crewProfile)
    {
        return view('admin.website.bio', $this->content->biography($crewProfile));
    }

    public function saveBio(SaveWebsiteBiographyRequest $request, CrewProfile $crewProfile)
    {
        $this->content->saveBiography($crewProfile, $request->validated(), $request->user());

        return back()->with('status', $this->message($request->validated('action')));
    }

    public function help()
    {
        return view('admin.website.help', ['articles' => $this->content->helpArticles()]);
    }

    public function article(?CustomerHelpArticle $article = null)
    {
        return view('admin.website.article', ['article' => $article]);
    }

    public function saveArticle(SaveWebsiteHelpRequest $request, ?CustomerHelpArticle $article = null)
    {
        $article = $this->content->saveHelp($article, $request->validated());

        return to_route('admin.website.help.edit', $article)->with('status', $this->message($request->validated('action')));
    }

    private function message(string $action): string
    {
        return match ($action) {
            'publish' => 'Published to the website.','hide' => 'Removed from the website. The draft is retained.',default => 'Draft saved. The published version has not changed.'
        };
    }
}
