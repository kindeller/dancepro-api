<?php

namespace App\Features\Admin\Requests;

use App\Features\Studios\Models\Studio;
use App\Features\Studios\Support\StudioStatus;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudioRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->string('code')->trim()->upper()->toString();

        $contacts = collect($this->input('contacts', []))
            ->map(fn ($contact) => is_array($contact) ? [
                'name' => trim((string) ($contact['name'] ?? '')),
                'role' => trim((string) ($contact['role'] ?? '')) ?: null,
                'emails' => collect(explode(',', (string) ($contact['emails'] ?? '')))
                    ->map(fn (string $email): string => mb_strtolower(trim($email)))
                    ->filter()
                    ->unique()
                    ->implode(', '),
                'phone' => trim((string) ($contact['phone'] ?? '')) ?: null,
            ] : [])
            ->filter(fn (array $contact): bool => collect($contact)->filter()->isNotEmpty())
            ->values()
            ->all();

        $this->merge(['code' => $code !== '' ? $code : null, 'contacts' => $contacts]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageStudios') ?? false;
    }

    public function rules(): array
    {
        /** @var Studio|null $studio */
        $studio = $this->route('studio');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('studios', 'code')->ignore($studio)],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('studios', 'slug')->ignore($studio)],
            'status' => ['required', Rule::enum(StudioStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'contacts' => ['nullable', 'array', 'max:50'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.role' => ['nullable', 'string', 'max:255'],
            'contacts.*.emails' => ['nullable', 'string', 'max:2000', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $fail("The {$attribute} field contains an invalid email address: {$email}.");
                    }
                }
            }],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
