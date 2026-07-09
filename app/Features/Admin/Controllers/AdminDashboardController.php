<?php

namespace App\Features\Admin\Controllers;

use App\Features\Downloads\Models\DownloadAccess;
use App\Features\Downloads\Models\DownloadLink;
use App\Features\Downloads\Support\DownloadLinkStatus;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', DownloadLink::class);

        $totals = [
            'links' => DownloadLink::query()->count(),
            'active' => DownloadLink::query()->where('status', DownloadLinkStatus::ACTIVE)->count(),
            'expired' => DownloadLink::query()->where('status', DownloadLinkStatus::EXPIRED)->count(),
            'revoked' => DownloadLink::query()->where('status', DownloadLinkStatus::REVOKED)->count(),
            'accesses' => DownloadAccess::query()->count(),
            'successful_accesses' => DownloadAccess::query()->where('was_successful', true)->count(),
        ];

        $recentLinks = DownloadLink::query()
            ->with('generatedBy')
            ->latest()
            ->limit(8)
            ->get();

        $recentAccesses = DownloadAccess::query()
            ->with('downloadLink')
            ->latest('accessed_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'totals' => $totals,
            'recentLinks' => $recentLinks,
            'recentAccesses' => $recentAccesses,
        ]);
    }
}
