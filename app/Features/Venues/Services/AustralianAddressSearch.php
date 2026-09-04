<?php

namespace App\Features\Venues\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AustralianAddressSearch
{
    public function search(string $query): array
    {
        $key = (string) config('services.google_maps.server_key');
        if (blank($key)) {
            return [];
        }

        return Cache::remember('address-search:'.hash('sha256', mb_strtolower(trim($query))), now()->addDay(), function () use ($query, $key): array {
            try {
                $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                    'input' => trim($query),
                    'components' => 'country:au',
                    'types' => 'address',
                    'key' => $key,
                ]);
            } catch (Throwable) {
                return [];
            }
            if (! $response->successful() || $response->json('status') !== 'OK') {
                return [];
            }

            return collect($response->json('predictions', []))->take(10)->map(fn (array $result): array => [
                'id' => $result['place_id'],
                'label' => $result['description'],
            ])->values()->all();
        });
    }

    public function resolve(string $placeId): ?array
    {
        $key = (string) config('services.google_maps.server_key');
        if (blank($key)) {
            return null;
        }

        return Cache::remember('address-details:'.hash('sha256', $placeId), now()->addDays(30), function () use ($placeId, $key): ?array {
            try {
                $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $placeId,
                    'fields' => 'formatted_address,address_components',
                    'key' => $key,
                ]);
            } catch (Throwable) {
                return null;
            }
            if (! $response->successful() || $response->json('status') !== 'OK') {
                return null;
            }

            $components = collect($response->json('result.address_components', []));
            $component = fn (string $type, string $field = 'long_name'): ?string => $components
                ->first(fn (array $item): bool => in_array($type, $item['types'] ?? [], true))[$field] ?? null;
            if ($component('country', 'short_name') !== 'AU') {
                return null;
            }
            $streetNumber = $component('street_number');
            $route = $component('route');
            $lineOne = trim(collect([$streetNumber, $route])->filter()->implode(' '));
            $suburb = $component('locality') ?? $component('postal_town') ?? $component('sublocality');
            $state = $component('administrative_area_level_1', 'short_name');
            $postcode = $component('postal_code');

            return [
                'label' => collect([
                    $lineOne,
                    collect([$suburb, $state, $postcode])->filter()->implode(' '),
                ])->filter()->implode(', '),
                'line_1' => $lineOne ?: null,
                'line_2' => null,
                'suburb' => $suburb,
                'state' => $state,
                'postcode' => $postcode,
            ];
        });
    }
}
