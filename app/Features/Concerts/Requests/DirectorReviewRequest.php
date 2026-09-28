<?php

namespace App\Features\Concerts\Requests;

use App\Features\Concerts\Services\ConcertDirectorReviews;
use Illuminate\Foundation\Http\FormRequest;

class DirectorReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && app(ConcertDirectorReviews::class)->canReview($this->user(), $this->route('schedulingEvent'));
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [];
        }

        return ['revision' => ['required', 'integer', 'min:0'], 'batch_uuid' => ['required', 'uuid'],
            'feedback' => [$this->routeIs('customer.concert-review.feedback') ? 'required' : 'nullable', 'string', 'max:8000'],
            'confirm_release' => [$this->routeIs('customer.concert-review.release') ? 'accepted' : 'sometimes']];
    }
}
