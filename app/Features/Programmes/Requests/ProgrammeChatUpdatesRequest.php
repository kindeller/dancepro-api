<?php

namespace App\Features\Programmes\Requests;

use App\Features\Chat\Services\CrewChatInbox;
use Illuminate\Foundation\Http\FormRequest;

class ProgrammeChatUpdatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }
        app(CrewChatInbox::class)->accessibleEvent($this->user(), $this->route('schedulingEvent'));

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
