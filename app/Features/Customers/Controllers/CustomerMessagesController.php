<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Actions\CreateCustomerSupportRequest;
use App\Features\Customers\Models\CustomerInboxNotification;
use App\Features\Customers\Models\CustomerSupportMessage;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Requests\CustomerMessagesRequest;
use App\Features\Customers\Requests\ReplyCustomerConversationRequest;
use App\Features\Customers\Requests\StoreCustomerSupportRequest;
use App\Features\Customers\Services\CustomerConversations;
use App\Features\Customers\Services\CustomerInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerMessagesController extends Controller
{
    public function index(CustomerMessagesRequest $request, CustomerInbox $inbox)
    {
        return response()->view('customers.messages.index', $inbox->page($request->user(), $request->validated('tab', 'notifications')))->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreCustomerSupportRequest $request, CreateCustomerSupportRequest $create)
    {
        $conversation = $create->execute($request->user(), $request->validated());

        return redirect()->route('customer.messages.show', $conversation)->with('status', 'Your message has been sent to DancePro.');
    }

    public function show(Request $request, CustomerSupportRequest $conversation, CustomerConversations $conversations, CustomerInbox $inbox)
    {
        abort_unless($request->user()->canAccessCustomer(), 403);
        $data = $conversations->page($conversation, $request->user());

        return response()->view('customers.messages.show', $data + $inbox->page($request->user(), 'conversations'))->header('Cache-Control', 'private, no-store');
    }

    public function reply(ReplyCustomerConversationRequest $request, CustomerSupportRequest $conversation, CustomerConversations $conversations)
    {
        $conversations->reply($conversation, $request->user(), $request->validated());

        return back()->with('status', 'Your reply has been sent to DancePro.');
    }

    public function read(CustomerMessagesRequest $request, CustomerInboxNotification $notification, CustomerInbox $inbox)
    {
        $inbox->read($notification, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Notification marked as read.',
                'data' => ['counts' => $inbox->counts($request->user())]])->header('Cache-Control', 'private, no-store');
        }

        return back()->with('status', 'Notification marked as read.');
    }

    public function attachment(CustomerMessagesRequest $request, CustomerSupportMessage $message, CustomerConversations $conversations)
    {
        return $conversations->attachment($message, $request->user());
    }
}
