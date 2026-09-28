<?php

namespace App\Features\StudioBilling\Services;

use App\Features\StudioBilling\Models\StudioInvoice;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class StudioInvoicePdf
{
    public function download(StudioInvoice $invoice): Response
    {
        return response($this->bytes($invoice), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="DancePro-Invoice-'.$invoice->number().'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function bytes(StudioInvoice $invoice): string
    {
        $invoice->load('studio', 'payments');
        $url = route('customer.studio-invoices.show', [$invoice->studio, $invoice]);
        $qr = 'data:image/png;base64,'.base64_encode((new Writer(new GDLibRenderer(160)))->writeString($url));
        $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/brand/dancepro-logo-colour-landscape.png')));
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => public_path()]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('studio-invoices.pdf', compact('invoice', 'url', 'qr', 'logo'))->render());
        $pdf->setPaper('A4');
        $pdf->render();
        $pdf->getCanvas()->page_text(480, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.4, 0.45, 0.5]);

        return $pdf->output();
    }
}
