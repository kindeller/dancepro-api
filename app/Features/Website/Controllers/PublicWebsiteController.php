<?php

namespace App\Features\Website\Controllers;

use App\Features\Customers\Services\CustomerHelpCentre;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Features\Website\Services\WebsiteContent;
use App\Features\Website\Services\WebsiteTerms;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PublicWebsiteController extends Controller
{
    public function __construct(private readonly WebsiteContent $content) {}

    public function home()
    {
        return view('website.page', $this->content->page('home'));
    }

    public function about()
    {
        return view('website.page', $this->content->page('about'));
    }

    public function services()
    {
        return view('website.page', $this->content->page('services'));
    }

    public function detail(Request $request, string $websiteSlug, WebsiteTerms $terms)
    {
        if (in_array($websiteSlug, ['terms', 'privacy'], true)) {
            $tab = $websiteSlug === 'privacy' ? 'privacy' : $request->query('tab', 'overview');
            abort_unless(is_string($tab), 404);

            return view('website.terms', $terms->page($tab));
        }

        return view('website.page', $this->content->page($websiteSlug));
    }

    public function help(Request $request, CustomerHelpCentre $help, MediaCommerceSettings $settings)
    {
        $query = mb_substr(trim((string) $request->query('q')), 0, 255);

        return view('customers.help', ['query' => $query, 'content' => $settings->get('help_content'), 'articleGroups' => $help->articles($query, $request->user())->groupBy('category'), 'publicHelp' => true]);
    }
}
