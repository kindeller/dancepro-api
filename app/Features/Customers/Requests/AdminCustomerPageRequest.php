<?php

namespace App\Features\Customers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCustomerPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['tab' => ['nullable', Rule::in(['overview', 'insights', 'orders', 'credit', 'access', 'favourites', 'cart', 'downloads', 'communications', 'notes', 'activity', 'settings', 'privacy', 'security'])], 'event' => ['nullable', 'uuid']];
        foreach (['orders', 'creditTransactions', 'supportRequests', 'notifications', 'accountEmails', 'downloads', 'competitionAccess', 'concertGrants', 'concertAccess', 'activity', 'engagement', 'loginEvents', 'riskEvents', 'notes', 'consentHistory', 'privacyRequests'] as $name) {
            $rules[$name.'_page'] = ['nullable', 'integer', 'min:1'];
        }

        return $rules;
    }
}
