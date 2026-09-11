<?php

namespace Tests\Feature\Timesheets;

use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Tests\TestCase;

class InvoicePrintLayoutTest extends TestCase
{
    public function test_standard_invoices_fit_one_a4_portrait_page_in_every_style(): void
    {
        foreach (['classic', 'minimal', 'modern'] as $style) {
            $invoice = (object) [
                'invoice_number' => 'LAYOUT-2026-001',
                'invoice_style' => $style,
                'submitted_at' => Carbon::parse('2026-09-11'),
                'total' => 1600,
                'issuer_snapshot' => [
                    'legal_name' => 'Example Crew Contractor', 'address_line_1' => '123 Example Street',
                    'address_line_2' => '', 'suburb' => 'Perth', 'state' => 'WA', 'postcode' => '6000',
                    'phone' => '0400 000 000', 'abn' => '00 000 000 000',
                    'bank_account_name' => 'Example Contractor', 'bank_name' => 'Example Bank',
                    'bank_bsb' => '000000', 'bank_account_number' => '00000000',
                ],
                'lines' => collect(range(1, 8))->map(fn ($number) => (object) [
                    'snapshot' => ['date' => '2026-09-10', 'event' => 'Dance studio concert rehearsal portraits '.$number,
                        'role' => 'Photographer', 'start' => '15:00', 'finish' => '19:00', 'hours' => 4, 'rate' => 50],
                    'allowance_amount' => 0, 'line_total' => 200,
                ]),
            ];
            $options = new Options;
            $options->set('defaultMediaType', 'print');
            $pdf = new Dompdf($options);
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml(view('invoices.print', ['invoice' => $invoice, 'pdf' => true])->render());
            $pdf->render();
            if (getenv('INVOICE_LAYOUT_PREVIEW')) {
                file_put_contents('/tmp/invoice-layout-'.$style.'.pdf', $pdf->output());
            }
            $this->assertSame(1, $pdf->getCanvas()->get_page_count(), $style);
            $this->assertEqualsWithDelta(595.28, $pdf->getCanvas()->get_width(), 0.1);
            $this->assertEqualsWithDelta(841.89, $pdf->getCanvas()->get_height(), 0.1);
        }
    }
}
