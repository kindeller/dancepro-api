<?php

namespace App\Features\Customers\Requests;

class GuestStudioSubscriptionRequest extends GuestGalleryRegistrationRequest
{
    public function rules(): array
    {
        return ($this->user() ? [] : ['email' => ['required', 'email', 'max:255']])
            + ['notification_consent' => ['required', 'accepted']];
    }
}
