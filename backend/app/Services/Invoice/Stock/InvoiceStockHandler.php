<?php

namespace App\Services\Invoice\Stock;

use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceProduct;
use App\Models\Product;

/**
 * The stock policy for one invoice type. The InvoiceService orchestrates the
 * shared flow (header, taxes, totals, audit) and defers the parts that differ
 * between purchases and sales to an implementation of this interface.
 */
interface InvoiceStockHandler
{
    /** Validate that the party is a legitimate counterparty for this invoice type. */
    public function assertParty(int $partyId, int $storeId): void;

    /** Link the party to this store — it has now transacted here. */
    public function linkPartyToStore(int $partyId, int $storeId): void;

    /** Apply one line's effect on inventory (a purchase adds stock; a sale draws it down). */
    public function applyLine(
        Invoice $invoice,
        InvoiceProduct $line,
        Product $product,
        float $quantity,
        float $unitPrice,
        string $invoiceDate,
    ): void;

    /**
     * Establish a line's *supply* side while rebuilding an invoice during a re-flow:
     * a purchase creates its batch; a sale contributes no supply, since its draw is
     * re-applied when the product's sales are replayed.
     */
    public function establishSupply(
        Invoice $invoice,
        InvoiceProduct $line,
        Product $product,
        float $quantity,
        float $unitPrice,
        string $invoiceDate,
    ): void;

    /** Undo all of this invoice's stock effects, for an edit or a delete. */
    public function reverse(Invoice $invoice): void;
}
