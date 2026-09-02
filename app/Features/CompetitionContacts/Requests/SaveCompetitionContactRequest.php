<?php

namespace App\Features\CompetitionContacts\Requests;

use App\Features\CompetitionContacts\Models\CompetitionContact;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompetitionContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageScheduling') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->upper()->toString();
        $staff = collect($this->input('staff', []))->map(fn ($person) => is_array($person) ? [
            'name' => trim((string) ($person['name'] ?? '')),
            'role' => trim((string) ($person['role'] ?? '')) ?: null,
            'emails' => collect(explode(',', (string) ($person['emails'] ?? '')))
                ->map(fn (string $email): string => mb_strtolower(trim($email)))
                ->filter()->unique()->implode(', '),
            'phone' => trim((string) ($person['phone'] ?? '')) ?: null,
        ] : [])->filter(fn (array $person): bool => collect($person)->filter()->isNotEmpty())->values()->all();

        $this->merge([
            'code' => $code !== '' ? $code : null,
            'is_active' => $this->boolean('is_active'),
            'staff' => $staff,
        ]);
    }

    public function rules(): array
    {
        /** @var CompetitionContact|null $contact */
        $contact = $this->route('competition_contact');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('competition_contacts', 'code')->ignore($contact)],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'staff' => ['required', 'array', 'min:1', 'max:50'],
            'staff.*.name' => ['required', 'string', 'max:255'],
            'staff.*.role' => ['nullable', 'string', 'max:255'],
            'staff.*.emails' => ['required', 'string', 'max:2000', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $fail("The {$attribute} field contains an invalid email address: {$email}.");
                    }
                }
            }],
            'staff.*.phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['present', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
