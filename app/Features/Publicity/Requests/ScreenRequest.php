<?php

namespace App\Features\Publicity\Requests;

use App\Features\Publicity\Models\TvScreen;
use Illuminate\Foundation\Http\FormRequest;

class ScreenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cached_revision' => ['nullable', 'string', 'size:64'], 'cached_count' => ['sometimes', 'integer', 'between:0,100000']];
    }

    public function screen(): TvScreen
    {
        $token = $this->bearerToken();
        abort_unless(is_string($token) && strlen($token) === 64, 401);
        $screen = TvScreen::where('token_hash', hash('sha256', $token))->first();
        abort_unless($screen && ! $screen->revoked_at && $screen->token_expires_at->isFuture(), 401);
        abort_if(! $screen->paired_at && $screen->pairing_expires_at?->isPast(), 401);

        return $screen;
    }
}
