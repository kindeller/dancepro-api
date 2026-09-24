<?php

namespace App\Features\Reporting\Controllers;

use App\Features\Reporting\Requests\MediaSalesReportRequest;
use App\Features\Reporting\Services\MediaSalesReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMediaSalesController extends Controller
{
    public function index(MediaSalesReportRequest $request, MediaSalesReport $report): Response
    {
        $filters = $request->validated();
        $activeTab = $filters['tab'] ?? 'overview';
        $results = $report->report($filters);
        $orders = $report->query($filters)->withCount('items')->latest('paid_at')->paginate(50)->withQueryString();

        return response()->view('admin.media.sales', compact('activeTab', 'filters', 'results', 'orders'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(MediaSalesReportRequest $request, MediaSalesReport $report): StreamedResponse
    {
        $filters = $request->safe()->except('tab');
        $orders = $report->query($filters)->withCount('items')->latest('paid_at')->cursor();

        return response()->streamDownload(function () use ($orders): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order', 'Paid at', 'Customer', 'Email', 'Type', 'Items', 'Subtotal', 'Discounts', 'Account credit', 'GST included', 'Paid total']);
            foreach ($orders as $order) {
                fputcsv($output, [$order->uuid, $order->paid_at?->toIso8601String(), $order->customer_name, $order->customer_email,
                    data_get($order->metadata, 'kind'), $order->items_count, $order->subtotal_amount / 100,
                    data_get($order->metadata, 'discount_amount', 0) / 100,
                    data_get($order->metadata, 'account_credit_amount', 0) / 100,
                    $order->tax_amount / 100, $order->total_amount / 100]);
            }
            fclose($output);
        }, 'dancepro-media-sales-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
