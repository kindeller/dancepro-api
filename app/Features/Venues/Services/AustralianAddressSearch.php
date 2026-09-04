<?php

namespace App\Features\Venues\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AustralianAddressSearch
{
    public function search(string $query): array
    {
        $key = (string) config('services.google_maps.server_key');
        if (blank($key)) {
            return [];
        }

        return Cache::remember('address-search:'.hash('sha256', mb_strtolower(trim($query))), now()->addDay(), function () use ($query, $key): array {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => trim($query),
                'components' => 'country:au',
                'types' => 'address',
                'key' => $key,
            ]);
            if (! $response->successful() || $response->json('status') === 'ZERO_RESULTS') {
                return [];
            }
            $response->throw();

            return collect($response->json('predictions', []))->take(10)->map(fn (array $result): array => [
                'id' => $result['place_id'],
                'address' => $result['description'],
            ])->values()->all();
        });
    }
}
