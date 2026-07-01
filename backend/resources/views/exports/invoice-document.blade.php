@php
    use App\Enums\InvoiceTypeEnum;
    use App\Support\Money;
    use App\Support\NumberToWords;

    $fmtQty = static function ($value) {
        $trimmed = rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
        return $trimmed === '' ? '0' : $trimmed;
    };
    $fmtRate = static function ($value) {
        $trimmed = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        return $trimmed === '' ? '0' : $trimmed;
    };

    $locale = app()->getLocale();
    $isSale = $invoice->type === InvoiceTypeEnum::SALE;
    $balance = (float) $invoice->balance;
    $titleText = $isSale ? __('document.title_sale') : __('document.title_purchase');
    $spelled = $locale === 'vi' ? NumberToWords::vi($invoice->grand_total) : NumberToWords::en($invoice->grand_total);
    $amountWords = ucfirst($spelled).' '.__('document.currency_word');
    $invoiceDate = optional($invoice->invoice_date)->translatedFormat($locale === 'vi' ? 'd/m/Y' : 'F j, Y') ?? '—';

    $methodValue = $invoice->payment_method->value;
    $methodKey = 'document.method_'.$methodValue;
    $methodLabel = __($methodKey) === $methodKey ? ucfirst($methodValue) : __($methodKey);

    $party = $invoice->party?->customer ?? $invoice->party?->supplier;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 34px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11.5px; line-height: 1.4; }

        .head { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .store { font-size: 16px; font-weight: bold; color: #111; text-transform: uppercase; letter-spacing: 0.3px; }
        .store-sub { font-size: 10.5px; color: #6b7280; padding-top: 2px; }
        .store-sub .store-k { color: #9ca3af; }
        .head-right { text-align: right; width: 230px; }
        .kv { font-size: 11px; padding-top: 2px; }
        .kv .k { color: #9ca3af; }
        .kv .v { color: #111; font-weight: bold; padding-left: 6px; }

        .rule { border-bottom: 2px solid #111; margin: 12px 0 0; }

        .title { text-align: center; font-size: 19px; font-weight: bold; letter-spacing: 2px; color: #111; margin: 16px 0 4px; }
        .title-underline { width: 56px; border-bottom: 2px solid #111; margin: 0 auto 6px; }
        .title-date { text-align: center; font-size: 11.5px; font-style: italic; color: #4b5563; margin: 0 0 16px; }

        .party { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .party td { padding: 2px 0; font-size: 11.5px; vertical-align: top; }
        .party .p-label { width: 110px; color: #6b7280; white-space: nowrap; }
        .party .p-label:before { content: "- "; }
        .party .p-value { color: #111; font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.items th { background: #f3f4f6; border: 1px solid #9ca3af; padding: 7px 7px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; color: #374151; text-align: center; }
        table.items td { border: 1px solid #cbd1d9; padding: 6px 7px; font-size: 11.5px; vertical-align: top; }
        table.items td.num, table.items th.num { text-align: right; }
        table.items td.center { text-align: center; }
        .product { font-weight: bold; color: #111; }
        .tax-chip { display: inline-block; margin: 0 3px 2px 0; padding: 1px 6px; border: 1px solid #e5e7eb; border-radius: 3px; background: #f9fafb; color: #4b5563; font-size: 9.5px; }
        .muted { color: #9ca3af; }

        .summary { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .summary > tbody > tr > td { vertical-align: top; }
        .sum-left { padding-right: 18px; }
        .in-words { font-size: 11.5px; }
        .in-words .lbl { color: #6b7280; }
        .in-words .val { font-style: italic; color: #111; }
        .desc { margin-top: 8px; font-size: 11px; color: #4b5563; }
        .desc .lbl { color: #6b7280; }

        .sum-right { width: 252px; }
        table.totals { width: 100%; border-collapse: collapse; }
        table.totals td { padding: 5px 10px; font-size: 11.5px; border: 1px solid #cbd1d9; }
        table.totals td.t-label { color: #4b5563; background: #f9fafb; }
        table.totals td.t-value { text-align: right; color: #111; }
        table.totals tr.grand td { font-size: 13px; font-weight: bold; color: #111; background: #eef2ff; }
        table.totals tr.balance td.t-value { font-weight: bold; color: {{ $balance > 0 ? '#b45309' : '#111' }}; }

        table.signs { width: 100%; border-collapse: collapse; margin-top: 30px; }
        table.signs td { width: 33%; text-align: center; vertical-align: top; padding-bottom: 56px; }
        .sign-role { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; color: #111; }
        .sign-sub { font-size: 10px; font-style: italic; color: #9ca3af; padding-top: 2px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <div class="store">{{ $store?->name ?? '—' }}</div>
                <div class="store-sub"><span class="store-k">{{ __('document.store_address') }}</span> {{ $store?->address }}</div>
                <div class="store-sub"><span class="store-k">{{ __('document.store_phone') }}</span> {{ $store?->phone }}</div>
                <div class="store-sub"><span class="store-k">{{ __('document.store_email') }}</span> {{ $store?->email }}</div>
            </td>
            <td class="head-right">
                <div class="kv"><span class="k">{{ __('document.no') }}</span><span class="v">{{ $invoice->code }}</span></div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <div class="title">{{ $titleText }}</div>
    <div class="title-underline"></div>
    <div class="title-date">{{ $invoiceDate }}</div>

    <table class="party">
        <tr>
            <td class="p-label">{{ $partyLabel }}</td>
            <td class="p-value">{{ $invoice->party_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="p-label">{{ __('document.phone') }}</td>
            <td class="p-value">{{ $party?->phone }}</td>
        </tr>
        <tr>
            <td class="p-label">{{ __('document.address') }}</td>
            <td class="p-value">{{ $party?->address }}</td>
        </tr>
        <tr>
            <td class="p-label">{{ __('document.tax_code') }}</td>
            <td class="p-value">{{ $party?->tax_code }}</td>
        </tr>
        <tr>
            <td class="p-label">{{ __('document.payment_method') }}</td>
            <td class="p-value">{{ $methodLabel }}</td>
        </tr>
        <tr>
            <td class="p-label">{{ __('document.issued_by') }}</td>
            <td class="p-value">{{ $invoice->creator?->name ?? '—' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 28px;">{{ __('document.col_no') }}</th>
                <th>{{ __('document.col_product') }}</th>
                <th style="width: 54px;">{{ __('document.col_unit') }}</th>
                <th class="num" style="width: 56px;">{{ __('document.col_qty') }}</th>
                <th class="num" style="width: 92px;">{{ __('document.col_unit_price') }}</th>
                <th style="width: 122px;">{{ __('document.col_taxes') }}</th>
                <th class="num" style="width: 96px;">{{ __('document.col_subtotal') }}</th>
                <th class="num" style="width: 100px;">{{ __('document.col_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td class="product">{{ $item->product_name }}</td>
                    <td class="center">{{ $item->product?->unit?->name ?? '—' }}</td>
                    <td class="num">{{ $fmtQty($item->quantity) }}</td>
                    <td class="num">{{ Money::vnd($item->unit_price) }}</td>
                    <td>
                        @forelse ($item->taxes as $tax)
                            <span class="tax-chip">{{ $tax->tax_name }} {{ $fmtRate($tax->tax_rate) }}%</span>
                        @empty
                            <span class="muted">—</span>
                        @endforelse
                    </td>
                    <td class="num">{{ Money::vnd($item->subtotal) }}</td>
                    <td class="num">{{ Money::vnd($item->grand_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="sum-left">
                <div class="in-words"><span class="lbl">{{ __('document.amount_in_words') }}</span> <span class="val">{{ $amountWords }}.</span></div>
                @if ($invoice->description)
                    <div class="desc"><span class="lbl">{{ __('document.description') }}</span> {{ $invoice->description }}</div>
                @endif
            </td>
            <td class="sum-right">
                <table class="totals">
                    <tr>
                        <td class="t-label">{{ __('document.subtotal') }}</td>
                        <td class="t-value">{{ Money::vnd($invoice->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="t-label">{{ __('document.tax') }}</td>
                        <td class="t-value">{{ Money::vnd($invoice->tax_total) }}</td>
                    </tr>
                    <tr class="grand">
                        <td class="t-label">{{ __('document.grand_total') }}</td>
                        <td class="t-value">{{ Money::vnd($invoice->grand_total) }}</td>
                    </tr>
                    <tr>
                        <td class="t-label">{{ __('document.paid') }}</td>
                        <td class="t-value">{{ Money::vnd($invoice->paid_amount) }}</td>
                    </tr>
                    <tr class="balance">
                        <td class="t-label">{{ __('document.balance') }}</td>
                        <td class="t-value">{{ Money::vnd($balance) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="signs">
        <tr>
            <td>
                <div class="sign-role">{{ $isSale ? __('document.sign_buyer') : __('document.sign_supplier') }}</div>
                <div class="sign-sub">{{ __('document.sign_hint') }}</div>
            </td>
            <td>
                <div class="sign-role">{{ __('document.prepared_by') }}</div>
                <div class="sign-sub">{{ __('document.sign_hint') }}</div>
            </td>
            <td>
                <div class="sign-role">{{ __('document.authorized_by') }}</div>
                <div class="sign-sub">{{ __('document.sign_hint') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
