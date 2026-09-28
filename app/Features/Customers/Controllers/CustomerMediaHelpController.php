<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\CreateCustomerSupportRequest;
use App\Features\Customers\Models\CustomerHelpArticle;
use App\Features\Customers\Requests\SaveCustomerHelpFeedbackRequest;
use App\Features\Customers\Requests\StoreCustomerSupportRequest;
use App\Features\Customers\Services\CustomerHelpCentre;
use App\Features\Media\Services\MediaCommerceSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerMediaHelpController extends Controller
{
    public function index(Request $request, CustomerHelpCentre $helpCentre, MediaCommerceSettings $settings): Response|RedirectResponse
    {
        if ($request->filled('type') || $request->filled('order') || $request->filled('entitlement')) {
            return redirect()->route('customer.messages.index', ['tab' => 'new'] + $request->only('type', 'order', 'entitlement'));
        }
        $query = mb_substr(trim((string) $request->query('q')), 0, 255);
        $articles = $helpCentre->articles($query, $request->user());

        return response()->view('customers.help', [
            'query' => $query,
            'content' => $settings->get('help_content'),
            'articleGroups' => $articles->groupBy('category'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreCustomerSupportRequest $request, CreateCustomerSupportRequest $create): RedirectResponse
    {
        $conversation = $create->execute($request->user(), $request->validated());

        return redirect()->route('customer.messages.show', $conversation)->with('status', 'Your request has been sent to DancePro.');
    }

    public function feedback(SaveCustomerHelpFeedbackRequest $request, CustomerHelpArticle $article, CustomerHelpCentre $helpCentre): RedirectResponse
    {
        $helpCentre->feedback($article, $request->user(), $request->boolean('helpful'));

        return back()->with('status', 'Thanks for helping us improve this article.');
    }
}
