<?php

namespace App\Features\Bookings\Requests;

use App\Features\Admin\Requests\SaveStudioRequest;

class CreateStudioFromBookingRequest extends SaveStudioRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('manageScheduling') ?? false)
            && ($this->user()?->can('manageStudios') ?? false);
    }
}
