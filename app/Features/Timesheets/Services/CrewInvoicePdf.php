<?php

namespace App\Features\Timesheets\Services;

use App\Features\Operations\Services\OperationsFileStorage;
use App\Features\Timesheets\Models\CrewInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;

class CrewInvoicePdf
{
    public function __construct(private readonly OperationsFileStorage $files) {}

    /** @return array{path: string, checksum: string, bytes: int} */
    public function document(CrewInvoice $invoice): array
    {
        $invoice->loadMissing(['crewProfile.user', 'lines', 'schedulingEvent']);
        abort_unless($invoice->status !== 'draft' && $invoice->source === 'dancepro', 422);
        $path = 'generated-invoices/styles-v4/'.$invoice->uuid.'.pdf';
        $disk = $this->disk();

        if (! $disk->exists($path)) {
            $options = new Options;
            $options->set('defaultMediaType', 'print');
            $options->set('isRemoteEnabled', false);
            $options->set('isPhpEnabled', false);
            $options->set('isJavascriptEnabled', false);
            $dompdf = new Dompdf($options);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->loadHtml(View::make('invoices.print', ['invoice' => $invoice, 'pdf' => true])->render(), 'UTF-8');
            $dompdf->render();
            $disk->put($path, $dompdf->output());
        }

        $contents = $disk->get($path);

        return ['path' => $path, 'checksum' => hash('sha256', $contents), 'bytes' => strlen($contents)];
    }

    public function disk(): Filesystem
    {
        return $this->files->disk();
    }
}
