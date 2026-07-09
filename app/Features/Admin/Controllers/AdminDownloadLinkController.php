<?php

namespace App\Features\Admin\Controllers;

use App\Features\Admin\Requests\StoreAdminDownloadLinksRequest;
use App\Features\Downloads\Actions\CreateDownloadLinks;
use App\Features\Downloads\Actions\RevokeDownloadLink;
use App\Features\Downloads\Models\DownloadLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminDownloadLinkController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DownloadLink::class);

        $query = DownloadLink::query()
            ->with('generatedBy')
            ->withCount('accesses')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('uuid', 'like', "%{$search}%")
                    ->orWhere('storage_key', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return view('admin.download-links.index', [
            'downloadLinks' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', DownloadLink::class);

        return view('admin.download-links.create', [
            'allowedDisks' => config('downloads.allowed_disks', []),
            'defaultDisk' => config('downloads.default_disk', 's3_competitions'),
        ]);
    }

    public function store(StoreAdminDownloadLinksRequest $request, CreateDownloadLinks $createDownloadLinks): RedirectResponse
    {
        Gate::authorize('create', DownloadLink::class);

        /** @var User $user */
        $user = $request->user();

        $items = $createDownloadLinks
            ->handle(
                $user,
                $request->storageKeys(),
                $request->string('disk')->toString() ?: null,
                $request->integer('days') ?: null,
                $request->string('purpose')->toString() ?: null,
                $request->string('notes')->toString() ?: null,
            )
            ->map(fn (array $item): array => [
                'uuid' => $item['download_link']->uuid,
                'key' => $item['download_link']->storage_key,
                'url' => route('downloads.public.show', ['token' => $item['token']]),
                'expires_at' => $item['download_link']->expires_at?->toDayDateTimeString(),
            ])
            ->all();

        return redirect()
            ->route('admin.download-links.create')
            ->with('created_links', $items)
            ->with('status', count($items).' download link'.(count($items) === 1 ? '' : 's').' created.');
    }

    public function show(DownloadLink $downloadLink): View
    {
        Gate::authorize('view', $downloadLink);

        $downloadLink->load(['generatedBy', 'revokedBy']);

        $accesses = $downloadLink->accesses()
            ->latest('accessed_at')
            ->paginate(25);

        return view('admin.download-links.show', [
            'downloadLink' => $downloadLink,
            'accesses' => $accesses,
        ]);
    }

    public function revoke(Request $request, DownloadLink $downloadLink, RevokeDownloadLink $revokeDownloadLink): RedirectResponse
    {
        Gate::authorize('revoke', $downloadLink);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $revokeDownloadLink->handle($downloadLink, $user, $validated['reason'] ?? null);

        return redirect()
            ->route('admin.download-links.show', $downloadLink)
            ->with('status', 'Download link revoked.');
    }
}
