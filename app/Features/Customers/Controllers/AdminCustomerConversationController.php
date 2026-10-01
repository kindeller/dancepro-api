<?php

namespace App\Features\Customers\Controllers;

use App\Features\Customers\Models\CustomerSupportMessage;
use App\Features\Customers\Models\CustomerSupportRequest;
use App\Features\Customers\Requests\ReplyCustomerConversationRequest;
use App\Features\Customers\Services\CustomerConversations;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminCustomerConversationController extends Controller
{
    public function show(Request $request, CustomerSupportRequest $conversation, CustomerConversations $conversations)
    {
        return redirect()->route('admin.messages.index', ['channel' => data_get($conversation->context, 'message_audience', 'customer'), 'thread' => $conversation->uuid]);
    }

    public function reply(ReplyCustomerConversationRequest $request, CustomerSupportRequest $conversation, CustomerConversations $conversations)
    {
        $conversations->reply($conversation, $request->user(), $request->validated(), true);

        return back()->with('status', 'Reply added to the conversation and queued for email.');
    }

    public function attachment(Request $request, CustomerSupportMessage $message, CustomerConversations $conversations)
    {
        return $conversations->attachment($message, $request->user(), true);
    }
}
