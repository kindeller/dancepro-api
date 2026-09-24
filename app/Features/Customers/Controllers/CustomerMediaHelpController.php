<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\CreateCustomerSupportRequest;
use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Requests\SaveCustomerHelpFeedbackRequest;
use App\Features\Customers\Requests\StoreCustomerSupportRequest;
use App\Features\Customers\Services\CustomerHelpCentre;
use App\Features\Customers\Services\CustomerOrderHistory;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerMediaHelpController extends Controller
{
    public function index(Request $request, MediaCommerceSettings $settings, CustomerOrderHistory $orders, CustomerHelpCentre $helpCentre): Response
    {
        $query = mb_substr(trim((string) $request->query('q')), 0, 255);
        $articles = $helpCentre->articles($query, $request->user());

        return response()->view('customers.help', [
            'content' => $settings->get('help_content'),
            'query' => $query,
            'articleGroups' => $articles->groupBy('category'),
            'orders' => $orders->forUser($request->user(), 100),
            'requests' => CustomerSupportRequest::query()->where('user_id', $request->user()->id)
                ->with(['order', 'entitlement.orderItem'])->latest('requested_at')->get(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreCustomerSupportRequest $request, CreateCustomerSupportRequest $create): RedirectResponse
    {
        $create->execute($request->user(), $request->validated());

        return back()->with('status', 'Your request has been sent to DancePro.');
    }

    public function feedback(SaveCustomerHelpFeedbackRequest $request, CustomerHelpArticle $article, CustomerHelpCentre $helpCentre): RedirectResponse
    {
        $helpCentre->feedback($article, $request->user(), $request->boolean('helpful'));

        return back()->with('status', 'Thanks for helping us improve this article.');
    }
}
