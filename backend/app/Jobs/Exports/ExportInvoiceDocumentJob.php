<?php

namespace App\Jobs\Exports;

use App\Enums\InvoiceTypeEnum;
use App\Exports\BaseExport;
use App\Models\Export;
use App\Models\Invoice\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Invoice\InvoiceRepository;
use App\Services\AuditLog\Loggers\InvoiceAuditLogger;
use App\Services\ExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a ZIP archive containing one PDF document per matched invoice — the
 * full content (line items, taxes, totals, payment) of each invoice, rendered
 * from the exports.invoice-document Blade view. Used by the selector-driven
 * "Export PDF" action on the invoice pages.
 */
class ExportInvoiceDocumentJob extends AbstractFileExportJob
{
    public const TYPE = 'invoice-documents';

    protected function writeFile(Export $export, string $relative): void
    {
        $storeId = (int) ($export->metadata['scope_id'] ?? 0);
        $store = Store::find($storeId);
        $filters = $export->metadata['filters'] ?? [];

        $invoices = app(InvoiceRepository::class)->documentsQuery($storeId, $filters)->get();
        if ($invoices->isEmpty()) {
            throw new \RuntimeException('No invoices matched the export selection.');
        }

        $disk = Storage::disk(ExportService::DISK);
        $disk->makeDirectory(dirname($relative));
        $absolute = $disk->path($relative);

        $zip = new \ZipArchive();
        if ($zip->open($absolute, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the export archive.');
        }

        $usedNames = [];
        foreach ($invoices as $invoice) {
            $pdf = Pdf::loadView('exports.invoice-document', [
                'invoice'    => $invoice,
                'store'      => $store,
                'partyLabel' => $this->partyLabel($invoice->type),
            ])->setPaper('a4')->output();

            $zip->addFromString($this->entryName($invoice, $usedNames), $pdf);
        }

        $zip->close();
    }

    protected function filename(Export $export): string
    {
        $metadata = $export->metadata ?? [];
        $name = Store::find((int) ($metadata['scope_id'] ?? 0))?->name ?? ($metadata['scope_name'] ?? 'unknown');
        $slug = BaseExport::slugForFilename((string) $name);
        $prefix = $this->typePrefix($metadata['filters']['type'] ?? null);

        return "{$prefix}-{$slug}-".now()->format('YmdHis').'.zip';
    }

    protected function onCompleted(Export $export): void
    {
        $user = User::find($export->user_id);
        if (! $user) {
            return;
        }

        $metadata = $export->metadata ?? [];

        app(InvoiceAuditLogger::class)->invoiceExported(
            $user,
            (int) ($metadata['scope_id'] ?? 0),
            $export,
            $metadata['scope_name'] ?? '',
        );
    }

    /**
     * A unique, filesystem-safe "CODE.pdf" entry name for the archive,
     * disambiguating the rare case of two invoices sharing a sanitized code.
     */
    private function entryName(Invoice $invoice, array &$usedNames): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->code);
        $base = trim((string) $base, '-');
        if ($base === '') {
            $base = 'invoice-'.$invoice->id;
        }

        $name = $base;
        $suffix = 2;
        while (isset($usedNames[$name])) {
            $name = $base.'-'.$suffix;
            $suffix++;
        }
        $usedNames[$name] = true;

        return $name.'.pdf';
    }

    private function partyLabel(?InvoiceTypeEnum $type): string
    {
        return match ($type) {
            InvoiceTypeEnum::PURCHASE => 'Supplier',
            InvoiceTypeEnum::SALE     => 'Customer',
            default                   => 'Party',
        };
    }

    private function typePrefix(?string $type): string
    {
        return match (InvoiceTypeEnum::tryFrom((string) $type)) {
            InvoiceTypeEnum::PURCHASE => 'purchase-invoices',
            InvoiceTypeEnum::SALE     => 'sale-invoices',
            default                   => 'invoices',
        };
    }
}
