<?php

/*
|--------------------------------------------------------------------------
| Invoice extraction dictionary
|--------------------------------------------------------------------------
|
| Data that drives the deterministic (no-AI) invoice parser. The engine
| (DictionaryInvoiceParser) reads a PDF's normalized text and uses this file to
| find fields by their printed Vietnamese labels and to locate the line-item
| table. Adding support for a new invoice layout is mostly editing this file:
| add label synonyms here and a `profiles` entry pointing at the table markers.
|
| Labels are matched as "‹synonym› [optional (English)] :" so a synonym only
| matches where it's used as a field label, not anywhere it appears in prose.
|
*/

return [

    // Header fields → ordered label synonyms, grouped by party. First label found
    // (top-down) within that party's block wins. The seller block is read for a
    // purchase scan (→ supplier), the buyer block for a sale scan (→ customer).
    'party_fields' => [
        'seller' => [
            'name'     => ['Họ và tên người giao hàng', 'Đơn vị bán hàng', 'Người bán'],
            'tax_code' => ['Mã số thuế', 'MST'],
            'phone'    => ['Điện thoại', 'Số điện thoại'],
            'address'  => ['Địa chỉ'],
        ],
        'buyer' => [
            // "người nhận hàng" (recipient) covers warehouse goods-issue notes
            // (Phiếu xuất kho), which name the customer that way rather than "người mua".
            'name'     => ['Đơn vị mua hàng', 'Tên đơn vị', 'Họ và tên người mua hàng', 'Họ tên người mua hàng', 'Họ và tên người nhận hàng', 'Họ tên người nhận hàng', 'Tên khách hàng'],
            'tax_code' => ['Mã số thuế', 'MST'],
            'phone'    => ['Điện thoại', 'Số điện thoại'],
            'address'  => ['Địa chỉ'],
        ],
    ],

    // Document-wide fields, read regardless of which party the scan matches.
    'invoice_no' => ['Số'],

    // The buyer block begins at the first of these seen as a real "‹marker› :"
    // field (not a signature caption). A purchase scan stops reading seller fields
    // here; a sale scan starts reading the buyer (customer) fields here.
    'buyer_markers' => ['Họ tên người mua hàng', 'Họ và tên người mua hàng', 'Họ và tên người nhận hàng', 'Họ tên người nhận hàng', 'Đơn vị mua hàng', 'Người mua hàng', 'Tên khách hàng'],

    // Footer phrases identifying the e-invoice solution provider's own line. Those
    // lines carry the provider's tax code; skip them when reading supplier fields.
    'provider_noise' => ['cung cấp giải pháp', 'cung cấp dịch vụ', 'cung cấp bởi'],

    // Label synonyms for a document-wide VAT rate, applied to every line so the
    // review prefills the per-line tax (e.g. "Thuế suất GTGT (VAT rate): 10%").
    'vat_rate' => ['Thuế suất GTGT', 'Thuế suất'],

    // Total lines. When a line matches several of these, the longest synonym wins
    // (so "Cộng tiền hàng" is read as the subtotal, not the grand total's "Cộng").
    'totals' => [
        'subtotal'    => ['Cộng tiền hàng', 'Tiền hàng'],
        'vat_total'   => ['Tiền thuế GTGT', 'Thuế GTGT'],
        'grand_total' => ['Tổng cộng tiền thanh toán', 'Tiền thanh toán', 'Cộng'],
    ],

    // Column-header synonyms, for identifying which columns a table prints.
    'columns' => [
        'name'       => ['Tên hàng hóa, dịch vụ', 'Tên, nhãn hiệu', 'Tên hàng hóa'],
        'code'       => ['Mã số'],
        'unit'       => ['Đơn vị tính', 'Đvt', 'ĐVT'],
        'quantity'   => ['Thực nhập', 'Số lượng'],
        'unit_price' => ['Đơn giá'],
        'line_total' => ['Thành tiền'],
        'tax_rate'   => ['Thuế suất GTGT'],
    ],

    // One entry per recognizable layout. `detect` markers must all be present for
    // the profile to claim a document; the table markers bound the item rows.
    'profiles' => [

        'vnpt_purchase_note' => [
            'detect'       => ['PHIẾU NHẬP KHO', 'người giao hàng'],
            'currency'     => 'VND',
            'has_vat'      => false,
            'table_start'  => '/^\s*A\s+B\s+C\s+D\s+1\s+2\s+3\s+4\s*$/u',
            'table_end'    => '/^\s*Cộng\b/u',
            'code_pattern' => '/HH\d{3,}/u',
        ],

        // Mẫu 04-VT goods-issue note (PHIẾU XUẤT KHO): the sale-side twin of the
        // purchase note above — same table layout, no VAT; the customer is the
        // recipient ("người nhận hàng"), read as the buyer for a sale scan.
        'vnpt_sale_note' => [
            'detect'       => ['PHIẾU XUẤT KHO', 'người nhận hàng'],
            'currency'     => 'VND',
            'has_vat'      => false,
            'table_start'  => '/^\s*A\s+B\s+C\s+D\s+1\s+2\s+3\s+4\s*$/u',
            'table_end'    => '/^\s*Cộng\b/u',
            'code_pattern' => '/HH\d{3,}/u',
        ],

        // VNPT sales bill (hóa đơn bán hàng): no VAT, no product-code column; the
        // table and totals print above the header in the extracted text.
        'vnpt_sales' => [
            'detect'       => ['HÓA ĐƠN BÁN HÀNG', 'vnpt-invoice'],
            'currency'     => 'VND',
            'has_vat'      => false,
            'table_start'  => '/^1 2 3 4 5 6=4x5$/u',
            'table_end'    => '/^HÓA ĐƠN BÁN HÀNG$/u',
            'code_pattern' => null,
        ],

        // Viettel VAT e-invoice: single document-wide rate, clean numeric rows,
        // no product-code column.
        'viettel_vat' => [
            'detect'       => ['HÓA ĐƠN GIÁ TRỊ GIA TĂNG', 'vinvoice.viettel.vn'],
            'currency'     => 'VND',
            'has_vat'      => true,
            'table_start'  => '/^1 2 3 4 5 6 = 4 x 5$/u',
            'table_end'    => '/^Cộng tiền hàng\b/u',
            'code_pattern' => null,
        ],

        // HILO (T-VAN) VAT e-invoice: the seller block sits at the foot of the
        // document and the subtotal/total are dislocated from their labels (both
        // derived). Product code is printed in parentheses, e.g. "(81H-022.15)".
        'hilo_vat' => [
            'detect'       => ['HÓA ĐƠN GIÁ TRỊ GIA TĂNG', 'hilo.com.vn'],
            'currency'     => 'VND',
            'has_vat'      => true,
            'table_start'  => '/^\(0\) 1 2 3 4 5=3x4$/u',
            'table_end'    => '/^Cộng tiền hàng\b/u',
            'code_pattern' => '/\(?\d{2,3}[A-Z][\w.\-]+\)?/u',
        ],

    ],

];
