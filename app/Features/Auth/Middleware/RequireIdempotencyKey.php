<?php

namespace App\Features\Auth\Middleware;

use App\Features\Auth\Models\ApiIdempotencyRecord;
use App\Shared\Responses\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || ! Str::isUuid($key)) {
            return ApiResponse::error('The given data was invalid.', [
                'idempotency_key' => ['A valid Idempotency-Key UUID header is required.'],
            ], 422);
        }

        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_int($userId) || ctype_digit((string) $userId), 401);
        $method = strtoupper($request->method());
        $target = '/'.ltrim($request->getRequestUri(), '/');
        $requestHash = hash('sha256', $method."\n".$target."\n".$request->getContent());
        $record = $this->claim((int) $userId, $key, $method, $target, $requestHash);

        if (! hash_equals($record->request_hash, $requestHash)) {
            return ApiResponse::error('This Idempotency-Key was already used for a different request.', status: 409);
        }

        if ($record->completed_at !== null) {
            return $this->replay($record);
        }

        if (! $record->wasRecentlyCreated) {
            return ApiResponse::error('A request with this Idempotency-Key is already in progress. Retry shortly.', status: 409)
                ->header('Retry-After', '2');
        }

        try {
            return DB::transaction(function () use ($next, $request, $record): Response {
                $response = $next($request);
                if ($response->getStatusCode() >= 500 || $response->getStatusCode() === 429) {
                    $record->delete();

                    return $response;
                }

                $record->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => (string) $response->getContent(),
                    'response_headers' => ['Content-Type' => $response->headers->get('Content-Type', 'application/json')],
                    'completed_at' => now(),
                ]);

                return $response;
            });
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }
    }

    private function claim(int $userId, string $key, string $method, string $target, string $requestHash): ApiIdempotencyRecord
    {
        ApiIdempotencyRecord::query()->where('user_id', $userId)->where('key', $key)
            ->where('expires_at', '<=', now())->delete();
        ApiIdempotencyRecord::query()->where('user_id', $userId)->where('key', $key)
            ->whereNull('completed_at')->where('created_at', '<=', now()->subMinutes(5))->delete();

        try {
            return ApiIdempotencyRecord::query()->create([
                'user_id' => $userId,
                'key' => $key,
                'request_method' => $method,
                'request_target' => $target,
                'request_hash' => $requestHash,
                'expires_at' => now()->addHours(max(1, (int) config('security.idempotency_retention_hours', 24))),
            ]);
        } catch (QueryException $exception) {
            $record = ApiIdempotencyRecord::query()->where('user_id', $userId)->where('key', $key)->first();
            throw_if($record === null, $exception);

            return $record;
        }
    }

    private function replay(ApiIdempotencyRecord $record): Response
    {
        return response((string) $record->response_body, (int) $record->response_status, $record->response_headers ?? [])
            ->header('Idempotency-Replayed', 'true');
    }
}
