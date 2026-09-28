<?php

namespace App\Features\Reporting\Controllers;

use App\Features\Orders\Models\Order;
use App\Features\Orders\Models\OrderMediaAssetDownload;
use App\Features\Orders\Services\OrderFinancials;
use App\Features\Reporting\Requests\GalleryVisitorReportRequest;
use App\Features\Reporting\Requests\MediaSalesReportRequest;
use App\Features\Reporting\Services\GalleryEngagementReport;
use App\Features\Reporting\Services\MediaSalesReport;
use App\Features\Studios\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMediaSalesController extends Controller
{
    public function index(MediaSalesReportRequest $request, MediaSalesReport $report): Response
    {
        $filters = $request->validated();
        $activeTab = $filters['tab'] ?? 'overview';
        $results = $report->report($filters + ['tab' => $activeTab]);
        $orders = $activeTab === 'orders' ? $report->query($filters)->with('user', 'items.mediaCollection.concert', 'refunds.items')->latest('paid_at')->paginate(50)->withQueryString() : null;
        $orders?->through(fn (Order $order) => app(OrderFinancials::class)->forReport($order, $filters['kind'] ?? null));
        $downloadSummary = [];
        $downloads = null;
        if ($activeTab === 'downloads') {
            $downloadQuery = OrderMediaAssetDownload::query()->with(['user', 'asset', 'entitlement.orderItem.order']);
            if ($filters['date_from'] ?? null) {
                $downloadQuery->whereDate('last_download_at', '>=', $filters['date_from']);
            }
            if ($filters['date_to'] ?? null) {
                $downloadQuery->whereDate('last_download_at', '<=', $filters['date_to']);
            }
            if ($filters['customer'] ?? null) {
                $term = $filters['customer'];
                $orderId = Order::idFromDisplayNumber($term);
                $downloadQuery->where(function ($query) use ($term, $orderId): void {
                    $query->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                    if ($orderId !== null) {
                        $query->orWhereHas('entitlement.orderItem.order', fn ($query) => $query->whereKey($orderId));
                    }
                });
            }
            $downloadSummary = [
                'files' => (clone $downloadQuery)->count(),
                'requests' => (int) (clone $downloadQuery)->sum('download_count'),
                'bytes' => (int) (clone $downloadQuery)->sum('bytes_requested'),
                'repeat_files' => (clone $downloadQuery)->where('download_count', '>', 1)->count(),
            ];
            $downloads = $activeTab === 'downloads' ? $downloadQuery->latest('last_download_at')->paginate(50)->withQueryString() : null;

        }

        return response()->view('admin.media.sales', compact('activeTab', 'filters', 'results', 'orders', 'downloadSummary', 'downloads'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(MediaSalesReportRequest $request, MediaSalesReport $report): StreamedResponse
    {
        $filters = $request->safe()->except('tab');
        $orders = $report->scopedOrders($filters)->sortByDesc('paid_at');

        return response()->streamDownload(function () use ($orders): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order', 'Paid at', 'Customer', 'Email', 'Type', 'Items', 'Subtotal', 'Discounts', 'Account credit', 'GST included', 'Cash collected', 'Completed refunds', 'Net receipts']);
            foreach ($orders as $order) {
                fputcsv($output, [$order->displayNumber(), $order->paid_at?->toIso8601String(), $order->customer_name, $order->customer_email,
                    data_get($order->metadata, 'kind'), $order->items->sum('quantity'), $order->subtotal_amount / 100,
                    data_get($order->metadata, 'discount_amount', 0) / 100,
                    data_get($order->metadata, 'account_credit_amount', 0) / 100,
                    $order->tax_amount / 100, $order->total_amount / 100, data_get($order->metadata, 'report_refunds', 0) / 100, data_get($order->metadata, 'report_net_receipts', 0) / 100]);
            }
            fclose($output);
        }, 'dancepro-media-sales-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function eventActivity(
        GalleryVisitorReportRequest $request,
        string $kind,
        string $eventUuid,
        GalleryEngagementReport $report,
    ): Response {
        $data = $report->activity($kind, $eventUuid, $request->validated());

        return response()->view('admin.media.gallery-activity', $data)
            ->header('Cache-Control', 'private, no-store');
    }

    public function exportEventActivity(
        GalleryVisitorReportRequest $request,
        string $kind,
        string $eventUuid,
        GalleryEngagementReport $report,
    ): StreamedResponse {
        $data = $report->activity($kind, $eventUuid, $request->safe()->except(['page', 'per_page']), false);

        return $this->streamActivityExport($data);
    }

    public function studioActivity(
        GalleryVisitorReportRequest $request,
        Studio $studio,
        GalleryEngagementReport $report,
    ): Response {
        $data = $report->studioActivity($studio, $request->validated());

        return response()->view('admin.media.gallery-activity', $data)
            ->header('Cache-Control', 'private, no-store');
    }

    public function exportStudioActivity(
        GalleryVisitorReportRequest $request,
        Studio $studio,
        GalleryEngagementReport $report,
    ): StreamedResponse {
        $data = $report->studioActivity($studio, $request->safe()->except(['page', 'per_page']), false);

        return $this->streamActivityExport($data);
    }

    private function streamActivityExport(array $data): StreamedResponse
    {
        $event = $data['event'];

        return response()->streamDownload(function () use ($data, $event): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Gallery', 'Type', 'Visitor', 'Account ID', 'Email', 'Access', 'First activity', 'Last activity', 'Views', 'Favourites', 'Cart items', 'Complimentary items', 'Orders', 'Paid total']);
            foreach ($data['visitors'] as $visitor) {
                fputcsv($output, [
                    $event['name'], ucfirst($event['kind']), $visitor['name'], $visitor['user']?->id,
                    $visitor['email'], $visitor['access'], $visitor['first_activity']?->toIso8601String(),
                    $visitor['last_activity']?->toIso8601String(), $visitor['views'], $visitor['favourites'],
                    $visitor['cart_items'], $visitor['complimentary'],
                    $visitor['orders']->pluck('model')->map(fn (Order $order) => $order->displayNumber())->implode(', '),
                    $visitor['paid_total'] / 100,
                ]);
            }
            fclose($output);
        }, str($event['name'])->slug().'-gallery-visitors-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
