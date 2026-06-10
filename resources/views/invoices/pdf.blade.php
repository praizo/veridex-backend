<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 26pt 30pt;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 9pt;
            line-height: 1.45;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .muted {
            color: #6b7280;
        }

        .primary {
            color: #0a1d43;
        }

        .label {
            color: #6b7280;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .7pt;
            text-transform: uppercase;
        }

        .value {
            color: #111827;
            font-weight: 700;
        }

        .watermark {
            position: fixed;
            top: 365pt;
            left: 35pt;
            right: 35pt;
            text-align: center;
            font-size: 54pt;
            font-weight: 900;
            letter-spacing: 8pt;
            color: #f4f6fb;
            z-index: -1;
            text-transform: uppercase;
        }

        .top-band {
            border-radius: 12pt;
            background: #f4f6fb;
            border: 1pt solid #dbe3f1;
            color: #0a1d43;
            padding: 0;
            margin-bottom: 18pt;
            overflow: hidden;
        }

        .brand-panel {
            background: #0a1d43;
            color: #ffffff;
            padding: 20pt 21pt;
            vertical-align: top;
        }

        .invoice-panel {
            background: #ffffff;
            color: #0a1d43;
            padding: 20pt 21pt;
            vertical-align: top;
            border-left: 1pt solid #dbe3f1;
        }

        .header-accent-row {
            height: 6pt;
        }

        .header-accent-main {
            background: #0a1d43;
            width: 64%;
        }

        .header-accent-soft {
            background: #c8d6f0;
            width: 24%;
        }

        .header-accent-light {
            background: #f0f4fb;
            width: 12%;
        }

        .brand-mark {
            width: 34pt;
            height: 34pt;
            border-radius: 8pt;
            background: #ffffff;
            text-align: center;
            display: inline-block;
            margin-right: 10pt;
            padding: 5pt;
        }

        .brand-name {
            font-size: 15pt;
            font-weight: 800;
            margin: 0 0 3pt;
            color: #ffffff;
        }

        .brand-trademark {
            font-size: 6pt;
            opacity: .72;
            vertical-align: top;
        }

        .brand-meta {
            color: #c8d6f0;
            font-size: 8pt;
        }

        .supplier-card {
            margin-top: 13pt;
            padding-top: 10pt;
            border-top: 1pt solid rgba(255,255,255,.18);
        }

        .supplier-name {
            color: #ffffff;
            font-size: 10pt;
            font-weight: 800;
            margin-bottom: 3pt;
        }

        .brand-strip {
            margin-top: 9pt;
            color: #c8d6f0;
            font-size: 7pt;
            font-weight: 700;
            letter-spacing: .8pt;
            text-transform: uppercase;
        }

        .invoice-title {
            text-align: right;
            font-size: 27pt;
            font-weight: 900;
            letter-spacing: .6pt;
            margin: 0;
            color: #0a1d43;
        }

        .invoice-number {
            text-align: right;
            color: #64748b;
            font-size: 9pt;
            margin-top: 3pt;
        }

        .invoice-ref-box {
            margin-top: 13pt;
            border-radius: 8pt;
            background: #f4f6fb;
            border: 1pt solid #dbe3f1;
            padding: 8pt 9pt;
        }

        .status-pill {
            display: inline-block;
            margin-top: 9pt;
            padding: 4pt 8pt;
            border-radius: 999pt;
            background: #f0f4fb;
            color: #0a1d43;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: .5pt;
            text-transform: uppercase;
        }

        .draft-pill {
            background: #f1f5f9;
            color: #475569;
        }

        .summary-grid {
            margin-bottom: 18pt;
        }

        .summary-card {
            border: 1pt solid #dbe3f1;
            border-radius: 8pt;
            padding: 11pt 12pt;
            background: #f8fafc;
            vertical-align: top;
        }

        .summary-card + .summary-card {
            border-left: 10pt solid #ffffff;
        }

        .party-section {
            margin-bottom: 20pt;
        }

        .party-card {
            width: 49%;
            border: 1pt solid #dbe3f1;
            border-radius: 9pt;
            padding: 13pt;
            vertical-align: top;
        }

        .party-gap {
            width: 2%;
        }

        .party-title {
            margin-top: 6pt;
            font-size: 12pt;
            font-weight: 800;
            color: #0a1d43;
        }

        .party-lines {
            color: #4b5563;
            font-size: 8pt;
            margin-top: 5pt;
        }

        .section-title {
            color: #0a1d43;
            font-size: 9pt;
            font-weight: 800;
            letter-spacing: .4pt;
            text-transform: uppercase;
            margin: 0 0 8pt;
        }

        .items {
            margin-top: 8pt;
            border: 1pt solid #dbe3f1;
            border-radius: 9pt;
            overflow: hidden;
        }

        .items th {
            background: #f0f4fb;
            color: #0a1d43;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: .5pt;
            text-transform: uppercase;
            padding: 8pt 8pt;
            border-bottom: 1pt solid #e5e7eb;
        }

        .items td {
            padding: 10pt 8pt;
            border-bottom: 1pt solid #eef2f7;
            vertical-align: top;
        }

        .items tr:last-child td {
            border-bottom: none;
        }

        .item-name {
            font-size: 9pt;
            font-weight: 800;
            color: #0a1d43;
        }

        .item-desc {
            margin-top: 3pt;
            color: #6b7280;
            font-size: 7.5pt;
        }

        .item-tax {
            margin-top: 5pt;
            color: #0a1d43;
            font-size: 7pt;
            font-weight: 700;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .center {
            text-align: center;
        }

        .bottom-section {
            margin-top: 20pt;
        }

        .compliance-card {
            width: 54%;
            vertical-align: top;
            border: 1pt solid #dbe3f1;
            border-radius: 9pt;
            background: #f4f6fb;
            padding: 12pt;
        }

        .totals-card {
            width: 42%;
            vertical-align: top;
            border: 1pt solid #e5e7eb;
            border-radius: 9pt;
            padding: 12pt;
        }

        .bottom-gap {
            width: 4%;
        }

        .qr {
            width: 82pt;
            height: 82pt;
            border: 1pt solid #dbe3f1;
            background: #ffffff;
            padding: 5pt;
            margin-right: 10pt;
        }

        .irn {
            font-size: 7pt;
            color: #0a1d43;
            word-break: break-all;
            line-height: 1.35;
        }

        .totals-table td {
            padding: 4pt 0;
        }

        .totals-table .total-line td {
            border-top: 1pt solid #e5e7eb;
            padding-top: 8pt;
        }

        .grand-total {
            margin-top: 8pt;
            background: #0a1d43;
            color: #ffffff;
            border-radius: 8pt;
            padding: 11pt 12pt;
        }

        .grand-total-label {
            color: #d1d5db;
            font-size: 8pt;
            font-weight: 800;
            text-transform: uppercase;
        }

        .grand-total-value {
            color: #ffffff;
            font-size: 15pt;
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }

        .notes {
            margin-top: 18pt;
            border-left: 3pt solid #0a1d43;
            background: #f4f6fb;
            padding: 11pt 12pt;
            color: #4b5563;
            font-size: 8pt;
        }

        .footer {
            position: fixed;
            left: 30pt;
            right: 30pt;
            bottom: 14pt;
            color: #9ca3af;
            font-size: 7pt;
            border-top: 1pt solid #dbe3f1;
            padding-top: 7pt;
        }
    </style>
</head>
<body>
@php
    $typeMap = [
        '380' => 'Commercial Invoice',
        '381' => 'Credit Note',
        '383' => 'Debit Note',
        '386' => 'Prepayment Invoice',
        '396' => 'Factored Invoice',
    ];

    $invoiceType = $typeMap[$invoice->invoice_type_code] ?? 'Standard Invoice';
    $status = $invoice->status?->value ?? $invoice->status;
    $paymentStatus = $invoice->payment_status?->value ?? $invoice->payment_status;
    $isFiscalized = in_array($status, ['signed', 'pending_transmit', 'transmit_failed', 'transmitted', 'confirmed'], true);
    $currency = $invoice->document_currency_code ?? 'NGN';
    $taxAmount = (float) $invoice->tax_inclusive_amount - (float) $invoice->tax_exclusive_amount;
@endphp

<div class="watermark">{{ $isFiscalized ? 'VERIDEX' : 'DRAFT' }}</div>

<div class="top-band">
    <table>
        <tr>
            <td class="brand-panel" style="width: 62%;">
                <table>
                    <tr>
                        <td style="width: 44pt; vertical-align: top;">
                            <div class="brand-mark">
                                <svg width="24" height="24" viewBox="0 0 26 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 5.5L13 2L23 5.5V13C23 18.5 18.5 22.5 13 24C7.5 22.5 3 18.5 3 13V5.5Z" stroke="#0a1d43" stroke-width="1.6" stroke-linejoin="round"/>
                                    <path d="M8 12.5L11.5 16L18 9.5" stroke="#0a1d43" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </td>
                        <td style="vertical-align: top;">
                            <h1 class="brand-name">Veridex<span class="brand-trademark">™</span></h1>
                            <div class="brand-meta">Trusted e-invoicing infrastructure</div>
                        </td>
                    </tr>
                </table>
                <div class="supplier-card">
                    <div class="supplier-name">{{ $invoice->organization->name }}</div>
                    <div class="brand-meta">
                        TIN: {{ $invoice->organization->tin ?? 'N/A' }}<br>
                        {{ $invoice->organization->street_name ?? '' }}{{ $invoice->organization->city_name ? ', '.$invoice->organization->city_name : '' }}<br>
                        {{ $invoice->organization->email ?? '' }}{{ $invoice->organization->telephone ? ' | '.$invoice->organization->telephone : '' }}
                    </div>
                </div>
                <div class="brand-strip">Veridex e-invoicing compliance document</div>
            </td>
            <td class="invoice-panel" style="width: 38%;">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
                <div class="invoice-ref-box">
                    <table>
                        <tr>
                            <td>
                                <div class="label">Document type</div>
                                <div class="value">{{ $invoiceType }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top: 6pt;">
                                <div class="label">Status</div>
                                <div class="status-pill {{ $isFiscalized ? '' : 'draft-pill' }}">
                                    {{ $isFiscalized ? 'FIRS signed' : 'Draft document' }}
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
        <tr class="header-accent-row">
            <td class="header-accent-main"></td>
            <td style="padding: 0;">
                <table>
                    <tr>
                        <td class="header-accent-soft"></td>
                        <td class="header-accent-light"></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<table class="summary-grid">
    <tr>
        <td class="summary-card" style="width: 25%;">
            <div class="label">Issue date</div>
            <div class="value">{{ $invoice->issue_date ? $invoice->issue_date->format('d M Y') : 'N/A' }}</div>
        </td>
        <td class="summary-card" style="width: 25%;">
            <div class="label">Due date</div>
            <div class="value">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'On receipt' }}</div>
        </td>
        <td class="summary-card" style="width: 25%;">
            <div class="label">Payment</div>
            <div class="value">{{ str_replace('_', ' ', (string) $paymentStatus) }}</div>
        </td>
        <td class="summary-card" style="width: 25%;">
            <div class="label">Currency</div>
            <div class="value">{{ $currency }}</div>
        </td>
    </tr>
</table>

<table class="party-section">
    <tr>
        <td class="party-card">
            <div class="label">Billed from</div>
            <div class="party-title">{{ $invoice->organization->name }}</div>
            <div class="party-lines">
                TIN: {{ $invoice->organization->tin ?? 'N/A' }}<br>
                {{ $invoice->organization->street_name ?? '' }}<br>
                {{ $invoice->organization->city_name ?? '' }}{{ $invoice->organization->country_subentity ? ', '.$invoice->organization->country_subentity : '' }}<br>
                {{ $invoice->organization->email ?? '' }}
            </div>
        </td>
        <td class="party-gap"></td>
        <td class="party-card">
            <div class="label">Billed to</div>
            <div class="party-title">{{ $invoice->customer->name }}</div>
            <div class="party-lines">
                TIN: {{ $invoice->customer->tin ?? 'N/A' }}<br>
                {{ $invoice->customer->street_name ?? '' }}{{ $invoice->customer->building_number ? ', No '.$invoice->customer->building_number : '' }}<br>
                {{ $invoice->customer->city_name ?? '' }}{{ $invoice->customer->country_subentity ? ', '.$invoice->customer->country_subentity : '' }}<br>
                {{ $invoice->customer->email ?? '' }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">Line items</div>
<table class="items">
    <thead>
        <tr>
            <th style="width: 42%; text-align: left;">Description</th>
            <th style="width: 12%;" class="center">Code</th>
            <th style="width: 10%;" class="center">Qty</th>
            <th style="width: 13%;" class="num">Unit price</th>
            <th style="width: 10%;" class="num">Tax</th>
            <th style="width: 13%;" class="num">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice->lines as $line)
            <tr>
                <td>
                    <div class="item-name">{{ $line->item_name }}</div>
                    @if($line->item_description)
                        <div class="item-desc">{{ $line->item_description }}</div>
                    @endif
                    <div class="item-tax">{{ $line->tax_category_id ?? 'VAT' }} at {{ number_format((float) $line->tax_percent, 1) }}%</div>
                </td>
                <td class="center muted">{{ $line->hscode ?? $line->hsn_code ?? '--' }}</td>
                <td class="center">{{ number_format((float) $line->invoiced_quantity, 2) }}</td>
                <td class="num">{{ number_format((float) $line->price_amount, 2) }}</td>
                <td class="num">{{ number_format(((float) $line->line_extension_amount * (float) $line->tax_percent) / 100, 2) }}</td>
                <td class="num value">{{ number_format((float) $line->line_extension_amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="bottom-section">
    <tr>
        <td class="compliance-card">
            <div class="section-title" style="margin-bottom: 7pt;">Compliance</div>
            @if($qrCodeSrc)
                <table>
                    <tr>
                        <td style="width: 96pt; vertical-align: top;">
                            <img src="{{ $qrCodeSrc }}" class="qr">
                        </td>
                        <td style="vertical-align: top;">
                            <div class="label" style="color: #0a1d43;">FIRS validation</div>
                            <div class="irn">{{ $invoice->irn }}</div>
                            <div class="muted" style="font-size: 7.5pt; margin-top: 7pt;">
                                This invoice has been signed for e-invoicing compliance. Scan the QR code or use the IRN for verification.
                            </div>
                        </td>
                    </tr>
                </table>
            @else
                <div class="muted" style="font-size: 8pt;">
                    This invoice has not been signed yet. Final e-invoicing validation details will appear after signing.
                </div>
            @endif
        </td>
        <td class="bottom-gap"></td>
        <td class="totals-card">
            <div class="section-title">Summary</div>
            <table class="totals-table">
                <tr>
                    <td class="muted">Subtotal</td>
                    <td class="num value">{{ number_format((float) $invoice->tax_exclusive_amount, 2) }}</td>
                </tr>
                @foreach($invoice->taxTotals as $tax)
                    <tr>
                        <td class="muted">{{ $tax->tax_category_id }} ({{ number_format((float) $tax->tax_percent, 1) }}%)</td>
                        <td class="num value">{{ number_format((float) $tax->tax_amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-line">
                    <td class="muted">Total tax</td>
                    <td class="num value">{{ number_format($taxAmount, 2) }}</td>
                </tr>
                @if((float) $invoice->allowance_total_amount > 0)
                    <tr>
                        <td class="muted">Discounts</td>
                        <td class="num value">-{{ number_format((float) $invoice->allowance_total_amount, 2) }}</td>
                    </tr>
                @endif
            </table>
            <table class="grand-total">
                <tr>
                    <td class="grand-total-label">Payable</td>
                    <td class="grand-total-value">{{ number_format((float) $invoice->payable_amount, 2) }} {{ $currency }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if($invoice->note || $invoice->payment_terms_note)
    <div class="notes">
        @if($invoice->note)
            <strong>Note:</strong> {{ $invoice->note }}<br>
        @endif
        @if($invoice->payment_terms_note)
            <strong>Payment terms:</strong> {{ $invoice->payment_terms_note }}
        @endif
    </div>
@endif

<div class="footer">
    Veridex e-invoicing document | {{ $invoice->invoice_number }} | {{ $currency }} | Generated {{ now()->format('d M Y H:i') }}
</div>
</body>
</html>
