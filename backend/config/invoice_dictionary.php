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

    // Header fields → ordered label synonyms. First label found (top-down) wins.
    'fields' => [
        'supplier_name'     => ['Họ và tên người giao hàng', 'Đơn vị bán hàng', 'Người bán'],
        'supplier_tax_code' => ['Mã số thuế', 'MST'],
        'supplier_phone'    => ['Điện thoại', 'Số điện thoại'],
        'supplier_address'  => ['Địa chỉ'],
        'invoice_no'        => ['Số'],
    ],

    // Once any of these is seen, stop reading supplier fields — the rest of the
    // document describes the buyer (us), whose name/tax code we must not capture.
    'buyer_markers' => ['Họ tên người mua hàng', 'Họ và tên người mua hàng', 'Đơn vị mua hàng', 'Người mua hàng'],

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

    ],

];
