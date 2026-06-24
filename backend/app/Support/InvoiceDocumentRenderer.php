<?php

namespace App\Support;

use App\Enums\InvoiceTypeEnum;
use App\Models\Invoice\Invoice;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Renders each invoice in a document export to its own PDF, driving the whole
 * set through a single shared Chromium (one browser launch per export, via the
 * render-invoices Puppeteer script) rather than cold-starting Chrome per file.
 */
class InvoiceDocumentRenderer
{
    /**
     * Write {outputDir}/{invoiceId}.pdf for every invoice in the collection.
     *
     * @param  Collection<int, Invoice>  $invoices
     */
    public function renderEach(Collection $invoices, ?Store $store, string $outputDir, string $locale = 'vi'): void
    {
        App::setLocale($locale);

        $items = $invoices->map(fn (Invoice $invoice) => [
            'html' => View::make('exports.invoice-document', [
                'invoice'    => $invoice,
                'store'      => $store,
                'partyLabel' => $this->partyLabel($invoice->type),
            ])->render(),
            'out' => $outputDir.'/'.$invoice->id.'.pdf',
        ])->all();

        $manifestPath = $outputDir.'/manifest.json';
        file_put_contents($manifestPath, json_encode([
            'chromePath' => config('pdf.chrome_path'),
            'margin'     => ['top' => '8mm', 'right' => '9mm', 'bottom' => '8mm', 'left' => '9mm'],
            'items'      => $items,
        ]));

        try {
            $result = Process::timeout(600)
                ->env(['NODE_PATH' => config('pdf.node_module_path')])
                ->run(['node', config('pdf.render_script'), $manifestPath]);

            if ($result->failed()) {
                throw new RuntimeException('Invoice PDF rendering failed: '.trim($result->errorOutput()));
            }
        } finally {
            @unlink($manifestPath);
        }
    }

    private function partyLabel(?InvoiceTypeEnum $type): string
    {
        return match ($type) {
            InvoiceTypeEnum::PURCHASE => __('document.party_supplier'),
            InvoiceTypeEnum::SALE     => __('document.party_customer'),
            default                   => __('document.party'),
        };
    }
}
