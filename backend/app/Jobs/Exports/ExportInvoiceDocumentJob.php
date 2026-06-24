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
use App\Support\InvoiceDocumentRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Builds a ZIP with one PDF per matched invoice. Every invoice in the export is
 * rendered through a single shared Chromium (see InvoiceDocumentRenderer), then
 * the per-invoice PDFs are zipped under their invoice codes.
 */
class ExportInvoiceDocumentJob extends AbstractFileExportJob
{
    public const TYPE = 'invoice-documents';

    public function __construct(int $exportId)
    {
        parent::__construct($exportId);
        $this->onQueue('exports');
    }

    protected function writeFile(Export $export, string $relative): void
    {
        $storeId = (int) ($export->metadata['scope_id'] ?? 0);
        $store = Store::find($storeId);
        $filters = $export->metadata['filters'] ?? [];
        $locale = $filters['locale'] ?? 'vi';

        $invoices = app(InvoiceRepository::class)->documentsQuery($storeId, $filters)->get();
        if ($invoices->isEmpty()) {
            throw new RuntimeException('No invoices matched the export selection.');
        }

        $disk = Storage::disk(ExportService::DISK);
        $disk->makeDirectory(dirname($relative));
        $disk->makeDirectory($export->id.'/pdfs');
        $workDir = $disk->path($export->id.'/pdfs');

        app(InvoiceDocumentRenderer::class)->renderEach($invoices, $store, $workDir, $locale);

        $this->zip($disk->path($relative), $invoices, $workDir);

        $disk->deleteDirectory($export->id.'/pdfs');
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    private function zip(string $absolute, Collection $invoices, string $workDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $usedNames = [];
        foreach ($invoices as $invoice) {
            $pdf = $workDir.'/'.$invoice->id.'.pdf';
            if (is_file($pdf)) {
                $zip->addFile($pdf, $this->entryName($invoice, $usedNames));
            }
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

    private function typePrefix(?string $type): string
    {
        return match (InvoiceTypeEnum::tryFrom((string) $type)) {
            InvoiceTypeEnum::PURCHASE => 'purchase-invoices',
            InvoiceTypeEnum::SALE     => 'sale-invoices',
            default                   => 'invoices',
        };
    }
}
