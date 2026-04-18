<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 10pt;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .sidebar {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 8px;
            background-color: #0a1d43; /* Veridex Brand Navy */
        }
        .container {
            margin-left: 8px;
            padding: 35pt 45pt;
        }
        .header {
            width: 100%;
            margin-bottom: 25pt;
        }
        .brand-section {
            width: 60%;
            vertical-align: top;
        }
        .logo-placeholder {
            width: 42pt;
            height: 42pt;
            background-color: #0a1d43;
            border-radius: 6pt;
            display: inline-block;
            text-align: center;
            line-height: 42pt;
            color: white;
            font-weight: 900;
            font-size: 22pt;
            margin-bottom: 12pt;
        }
        .org-name {
            font-size: 16pt;
            font-weight: 800;
            margin: 0;
            color: #0a1d43;
            letter-spacing: -0.5pt;
        }
        .org-details {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 5pt;
            line-height: 1.5;
        }
        .invoice-meta {
            width: 40%;
            text-align: right;
            vertical-align: top;
        }
        .title {
            font-size: 22pt;
            font-weight: 900;
            color: #0a1d43;
            text-transform: uppercase;
            letter-spacing: 1.5pt;
            margin: 0;
        }
        .type-badge {
            display: inline-block;
            background-color: #f1f5f9;
            color: #0a1d43;
            font-size: 7.5pt;
            font-weight: 800;
            padding: 2pt 6pt;
            border-radius: 10pt;
            margin-top: 5pt;
            text-transform: uppercase;
        }
        .meta-grid {
            margin-top: 12pt;
            font-size: 9pt;
        }
        .meta-item {
            color: #64748b;
            margin-bottom: 3pt;
        }
        .meta-value {
            font-weight: 700;
            color: #0a1d43;
        }
        
        .reference-section {
            width: 100%;
            margin-top: 25pt;
            background-color: #f8fafc;
            border: 1pt solid #f1f5f9;
            border-radius: 8pt;
            padding: 12pt;
        }
        .ref-grid {
            width: 100%;
        }
        .ref-box {
            width: 25%;
            vertical-align: top;
        }
        .ref-label {
            font-size: 7pt;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            margin-bottom: 2pt;
        }
        .ref-value {
            font-size: 8.5pt;
            font-weight: 700;
            color: #1e293b;
        }

        .billing-section {
            width: 100%;
            margin-top: 30pt;
            margin-bottom: 30pt;
        }
        .billing-box {
            width: 50%;
            vertical-align: top;
        }
        .section-label {
            font-size: 7.5pt;
            font-weight: 800;
            color: #0a1d43;
            text-transform: uppercase;
            letter-spacing: 1.2pt;
            margin-bottom: 8pt;
            border-bottom: 1pt solid #e2e8f0;
            display: inline-block;
            padding-bottom: 1pt;
        }
        .customer-name {
            font-size: 11pt;
            font-weight: 800;
            color: #1e293b;
            margin-top: 5pt;
        }
        .customer-details {
            font-size: 8.5pt;
            color: #475569;
            margin-top: 4pt;
            line-height: 1.4;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15pt;
        }
        .items-table th {
            text-align: left;
            padding: 8pt 10pt;
            background-color: #0a1d43;
            color: white;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }
        .items-table td {
            padding: 10pt;
            border-bottom: 1pt solid #f1f5f9;
            vertical-align: top;
        }
        .item-row:last-child td {
            border-bottom: 1.5pt solid #0a1d43;
        }
        .item-title {
            font-weight: 800;
            color: #0a1d43;
            font-size: 9.5pt;
        }
        .item-description {
            font-size: 8pt;
            color: #64748b;
            margin-top: 3pt;
            line-height: 1.3;
        }
        .item-meta {
            font-size: 7pt;
            color: #94a3b8;
            margin-top: 5pt;
            font-weight: 600;
        }
        .financial-section {
            margin-top: 25pt;
            width: 100%;
        }
        .compliance-box {
            width: 55%;
            vertical-align: top;
        }
        .totals-box {
            width: 45%;
            vertical-align: top;
        }
        .totals-table {
            width: 100%;
        }
        .totals-row {
            padding: 5pt 0;
            font-size: 9pt;
        }
        .totals-label {
            color: #64748b;
            font-weight: 500;
        }
        .totals-value {
            text-align: right;
            font-weight: 700;
            color: #1e293b;
        }
        .tax-breakdown-row {
            font-size: 8pt;
            color: #94a3b8;
            font-style: italic;
        }
        .grand-total-row {
            background-color: #0a1d43;
            padding: 12pt;
            border-radius: 4pt;
            margin-top: 8pt;
        }
        .grand-total-label {
            font-weight: 800;
            color: white;
            font-size: 11pt;
            vertical-align: middle;
        }
        .grand-total-value {
            text-align: right;
            font-weight: 900;
            color: white;
            font-size: 16pt;
            vertical-align: middle;
        }
        .notes-section {
            margin-top: 30pt;
            padding: 15pt;
            background-color: #fafafa;
            border-left: 2pt solid #0a1d43;
        }
        .notes-title {
            font-size: 8pt;
            font-weight: 800;
            color: #0a1d43;
            text-transform: uppercase;
            margin-bottom: 5pt;
        }
        .notes-content {
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.5;
        }
        .qr-section {
            margin-top: 5pt;
        }
        .qr-image {
            width: 75pt;
            height: 75pt;
            margin-bottom: 8pt;
        }
        .compliance-badge {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            font-size: 7pt;
            font-weight: 800;
            padding: 2pt 6pt;
            border-radius: 4pt;
            margin-bottom: 5pt;
        }
        .compliance-text {
            font-size: 7pt;
            color: #94a3b8;
            line-height: 1.4;
            max-width: 180pt;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 90pt;
            font-weight: 900;
            color: rgba(226, 232, 240, 0.25);
            z-index: -1;
            text-transform: uppercase;
            letter-spacing: 10pt;
        }
        .footer {
            position: absolute;
            bottom: 30pt;
            left: 45pt;
            right: 45pt;
            border-top: 1pt solid #f1f5f9;
            padding-top: 12pt;
            font-size: 7.5pt;
            color: #94a3b8;
            text-align: center;
        }
        .text-navy { color: #0a1d43; }
        .font-bold { font-weight: 700; }
        .font-black { font-weight: 900; }
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
    @endphp

    <div class="sidebar"></div>

    @if($invoice->status->value !== 'confirmed' && $invoice->status->value !== 'transmitted')
    <div class="watermark">DRAFT</div>
    @endif

    <div class="container">
        <!-- HEADER -->
        <table class="header">
            <tr>
                <td class="brand-section">
                    <div class="logo-placeholder">V</div>
                    <h1 class="org-name">{{ $invoice->organization->name }}</h1>
                    <div class="org-details">
                        <span class="font-bold text-navy">TIN:</span> {{ $invoice->organization->tin }}<br>
                        {{ $invoice->organization->street_name }}, {{ $invoice->organization->city_name }}<br>
                        {{ $invoice->organization->email }} | {{ $invoice->organization->telephone }}
                    </div>
                </td>
                <td class="invoice-meta">
                    <h2 class="title">Invoice</h2>
                    <div class="type-badge">{{ $invoiceType }}</div>
                    <div class="meta-grid">
                        <div class="meta-item">Number: <span class="meta-value">#{{ $invoice->invoice_number }}</span></div>
                        <div class="meta-item">Issued: <span class="meta-value">{{ $invoice->issue_date->format('d M, Y') }}</span> @if($invoice->issue_time) <span style="font-size: 8pt; opacity: 0.7;">{{ $invoice->issue_time }}</span> @endif</div>
                        <div class="meta-item">Due Date: <span class="meta-value">{{ $invoice->due_date ? $invoice->due_date->format('d M, Y') : 'On Receipt' }}</span></div>
                        @if($invoice->tax_point_date)
                        <div class="meta-item">Tax Point: <span class="meta-value">{{ $invoice->tax_point_date->format('d M, Y') }}</span></div>
                        @endif
                        <div class="meta-item">Currency: <span class="meta-value">{{ $invoice->document_currency_code }}</span></div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- REFERENCES -->
        <div class="reference-section">
            <table class="ref-grid" style="width: 100%;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div class="ref-label">Order Reference / PO #</div>
                        <div class="ref-value">{{ $invoice->order_reference ?? '--' }}</div>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <div class="ref-label">Payment Category</div>
                        <div class="ref-value">{{ $invoice->payment_status->value }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- BILLING -->
        <table class="billing-section">
            <tr>
                <td class="billing-box">
                    <div class="section-label">Recipient Information</div>
                    <div class="customer-name">{{ $invoice->customer->name }}</div>
                    <div class="customer-details">
                        <span class="font-bold text-navy">TIN:</span> {{ $invoice->customer->tin ?? 'N/A' }}<br>
                        {{ $invoice->customer->email }}<br>
                        {{ $invoice->customer->street_name }}@if($invoice->customer->building_number), No {{ $invoice->customer->building_number }} @endif<br>
                        {{ $invoice->customer->city_name }}, {{ $invoice->customer->country_subentity ?? '' }}
                    </div>
                </td>
                <td class="billing-box" style="text-align: right;">
                    @if($invoice->actual_delivery_date)
                    <div class="section-label">Delivery Context</div>
                    <div class="customer-details">
                        Delivery Date: <span class="font-bold text-navy">{{ $invoice->actual_delivery_date->format('d M, Y') }}</span><br>
                        @if($invoice->delivery_period_start)
                        Period: {{ $invoice->delivery_period_start->format('d M') }} - {{ $invoice->delivery_period_end->format('d M, Y') }}
                        @endif
                    </div>
                    @endif
                </td>
            </tr>
        </table>

        <!-- ITEMS -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="35%">Service / Item Description</th>
                    <th width="15%" style="text-align: center;">HS Code</th>
                    <th width="10%" style="text-align: center;">Qty</th>
                    <th width="10%" style="text-align: center;">Unit</th>
                    <th width="12%" style="text-align: right;">Price</th>
                    <th width="18%" style="text-align: right;">Amount ({{ $invoice->document_currency_code }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $line)
                <tr class="item-row">
                    <td>
                        <div class="item-title">{{ $line->item_name }}</div>
                        @if($line->item_description)
                        <div class="item-description">{{ $line->item_description }}</div>
                        @endif
                        <div class="item-meta">
                            Tax: {{ $line->tax_category_id }} ({{ number_format($line->tax_percent, 1) }}%)
                        </div>
                    </td>
                    <td style="text-align: center; vertical-align: top; padding-top: 15pt;">
                        <span class="font-mono text-xs">{{ $line->hscode ?? '--' }}</span>
                    </td>
                    <td style="text-align: center; font-weight: 700; padding-top: 15pt;">{{ number_format($line->invoiced_quantity, 0) }}</td>
                    <td style="text-align: center; color: #64748b; padding-top: 15pt; font-size: 8pt;">{{ $line->unit_code ?? 'PCE' }}</td>
                    <td style="text-align: right; padding-top: 15pt;">{{ number_format($line->price_amount, 2) }}</td>
                    <td style="text-align: right; font-weight: 800; color: #0a1d43; padding-top: 15pt;">{{ number_format($line->line_extension_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- FINANCIALS -->
        <table class="financial-section">
            <tr>
                <td class="compliance-box">
                    @if($qrCodeSrc)
                    <div class="qr-section">
                        <div class="compliance-badge">FIRS COMPLIANT</div><br>
                        <img src="{{ $qrCodeSrc }}" class="qr-image">
                        <div class="compliance-text">
                            <span class="font-bold text-navy">MBS Validation ID:</span><br>
                            {{ $invoice->irn }}<br><br>
                            This document has been digitally signed and transmitted to the National Revenue Service infrastructure.
                        </div>
                    </div>
                    @else
                    <div style="padding-top: 20pt;">
                        <div class="section-label">Important Note</div>
                        <div class="compliance-text">
                            This is a draft document and has not yet been submitted for official IRN signing.
                        </div>
                    </div>
                    @endif
                </td>
                <td class="totals-box">
                    <table class="totals-table">
                        <tr class="totals-row">
                            <td class="totals-label">Subtotal (Net)</td>
                            <td class="totals-value">{{ number_format($invoice->tax_exclusive_amount, 2) }}</td>
                        </tr>
                        @foreach($invoice->taxTotals as $tax)
                        <tr class="totals-row">
                            <td class="totals-label">{{ $tax->tax_category_id }} ({{ number_format($tax->tax_percent, 1) }}%)</td>
                            <td class="totals-value">{{ number_format($tax->tax_amount, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr class="totals-row">
                            <td class="totals-label">Total Tax Amount</td>
                            <td class="totals-value">{{ number_format($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount, 2) }}</td>
                        </tr>
                        @if($invoice->allowance_total_amount > 0)
                        <tr class="totals-row">
                            <td class="totals-label text-destructive">Discounts</td>
                            <td class="totals-value text-destructive">-{{ number_format($invoice->allowance_total_amount, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="grand-total-row">
                            <td class="grand-total-label">Payable Total</td>
                            <td class="grand-total-value">{{ number_format($invoice->payable_amount, 2) }} {{ $invoice->document_currency_code }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($invoice->note || $invoice->payment_terms_note)
        <div class="notes-section">
            @if($invoice->note)
            <div class="notes-title">Remarks / Instructions</div>
            <div class="notes-content">{{ $invoice->note }}</div>
            @endif
            
            @if($invoice->payment_terms_note)
            <div class="notes-title" style="margin-top: 10pt;">Payment Terms</div>
            <div class="notes-content">{{ $invoice->payment_terms_note }}</div>
            @endif
        </div>
        @endif

        <div class="footer">
            Veridex Compliance Engine | Document Authentication: {{ substr(md5($invoice->id . $invoice->created_at), 0, 12) }}
            <br>
            All amounts are in {{ $invoice->document_currency_code }}. Verified by FIRS MBS Gateway.
        </div>
    </div>
</body>
</html>
</body>
</html>
