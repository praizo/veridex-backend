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
            color: #1f2937;
            font-size: 11pt;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .sidebar {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 10px;
            background-color: #3b82f6; /* Veridex Primary */
        }
        .container {
            margin-left: 10px;
            padding: 40pt;
        }
        .header {
            width: 100%;
            margin-bottom: 30pt;
        }
        .brand-section {
            width: 50%;
            vertical-align: top;
        }
        .logo-placeholder {
            width: 48pt;
            height: 48pt;
            background-color: #3b82f6;
            border-radius: 8pt;
            display: inline-block;
            text-align: center;
            line-height: 48pt;
            color: white;
            font-weight: bold;
            font-size: 20pt;
            margin-bottom: 10pt;
        }
        .org-name {
            font-size: 18pt;
            font-weight: 800;
            margin: 0;
            color: #111827;
        }
        .org-details {
            font-size: 9pt;
            color: #6b7280;
            margin-top: 4pt;
        }
        .invoice-meta {
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        .title {
            font-size: 24pt;
            font-weight: 900;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 2pt;
            margin: 0;
        }
        .meta-grid {
            margin-top: 15pt;
            font-size: 10pt;
        }
        .meta-item {
            color: #6b7280;
            margin-bottom: 2pt;
        }
        .meta-value {
            font-weight: 700;
            color: #111827;
        }
        .billing-section {
            width: 100%;
            margin-top: 40pt;
            margin-bottom: 40pt;
        }
        .billing-box {
            width: 50%;
            vertical-align: top;
        }
        .section-label {
            font-size: 8pt;
            font-weight: 700;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: 1pt;
            margin-bottom: 8pt;
        }
        .customer-name {
            font-size: 12pt;
            font-weight: 700;
            color: #111827;
        }
        .customer-details {
            font-size: 9pt;
            color: #4b5563;
            margin-top: 4pt;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20pt;
        }
        .items-table th {
            text-align: left;
            padding: 10pt;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 2pt solid #e2e8f0;
        }
        .items-table td {
            padding: 12pt 10pt;
            border-bottom: 1pt solid #f1f5f9;
        }
        .item-description {
            font-weight: 600;
            color: #1e293b;
        }
        .item-subtext {
            font-size: 8pt;
            color: #94a3b8;
            margin-top: 2pt;
        }
        .financial-section {
            margin-top: 30pt;
            width: 100%;
        }
        .compliance-box {
            width: 60%;
            vertical-align: top;
        }
        .totals-box {
            width: 40%;
            vertical-align: top;
        }
        .totals-row {
            padding: 6pt 0;
            font-size: 10pt;
        }
        .totals-label {
            color: #64748b;
        }
        .totals-value {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
        }
        .grand-total-row {
            background-color: #f1f5f9;
            padding: 10pt;
            border-radius: 4pt;
            margin-top: 10pt;
        }
        .grand-total-label {
            font-weight: 800;
            color: #1e293b;
            font-size: 12pt;
        }
        .grand-total-value {
            text-align: right;
            font-weight: 900;
            color: #3b82f6;
            font-size: 16pt;
        }
        .qr-section {
            margin-top: 10pt;
            text-align: left;
        }
        .qr-image {
            width: 80pt;
            height: 80pt;
            margin-bottom: 5pt;
        }
        .compliance-text {
            font-size: 7.5pt;
            color: #94a3b8;
            line-height: 1.2;
            max-width: 200pt;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            font-weight: 900;
            color: rgba(226, 232, 240, 0.4);
            z-index: -1;
            text-transform: uppercase;
        }
        .footer {
            position: absolute;
            bottom: 40pt;
            left: 40pt;
            right: 40pt;
            border-top: 1pt solid #f1f5f9;
            padding-top: 15pt;
            font-size: 8pt;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="sidebar"></div>

    @if($invoice->status->value !== 'confirmed')
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
                        TIN: {{ $invoice->organization->tin }}<br>
                        {{ $invoice->organization->email }}<br>
                        {{ $invoice->organization->telephone }}
                    </div>
                </td>
                <td class="invoice-meta">
                    <h2 class="title">Invoice</h2>
                    <div class="meta-grid">
                        <div class="meta-item">Number: <span class="meta-value">#{{ $invoice->invoice_number }}</span></div>
                        <div class="meta-item">Issued: <span class="meta-value">{{ $invoice->issue_date->format('d M, Y') }}</span></div>
                        <div class="meta-item">Due: <span class="meta-value">{{ $invoice->due_date ? $invoice->due_date->format('d M, Y') : 'On Receipt' }}</span></div>
                        <div class="meta-item">Currency: <span class="meta-value">{{ $invoice->document_currency_code }}</span></div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- BILLING -->
        <table class="billing-section">
            <tr>
                <td class="billing-box">
                    <div class="section-label">Bill To</div>
                    <div class="customer-name">{{ $invoice->customer->name }}</div>
                    <div class="customer-details">
                        TIN: {{ $invoice->customer->tin ?? 'N/A' }}<br>
                        {{ $invoice->customer->email }}<br>
                        {{ $invoice->customer->street_name }}, {{ $invoice->customer->city_name }}
                    </div>
                </td>
                <td class="billing-box" style="text-align: right;">
                    <div class="section-label">Payment Status</div>
                    <div style="font-weight: 700; color: {{ $invoice->payment_status->value === 'PAID' ? '#10b981' : '#f59e0b' }};">
                        {{ $invoice->payment_status->value }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- ITEMS -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="50%">Description</th>
                    <th width="10%" style="text-align: center;">Qty</th>
                    <th width="20%" style="text-align: right;">Price</th>
                    <th width="20%" style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $line)
                <tr>
                    <td>
                        <div class="item-description">{{ $line->item_name }}</div>
                        <div class="item-subtext">HS Code: {{ $line->hscode }}</div>
                    </td>
                    <td style="text-align: center;">{{ number_format($line->invoiced_quantity, 0) }}</td>
                    <td style="text-align: right;">{{ number_format($line->price_amount, 2) }}</td>
                    <td style="text-align: right; font-weight: 700;">{{ number_format($line->line_extension_amount, 2) }}</td>
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
                        <div class="section-label">Compliance Signature</div>
                        <img src="{{ $qrCodeSrc }}" class="qr-image">
                        <div class="compliance-text">
                            Verified via National Revenue Service (NRS).<br>
                            IRN: {{ $invoice->irn }}
                        </div>
                    </div>
                    @endif
                </td>
                <td class="totals-box">
                    <table style="width: 100%;">
                        <tr class="totals-row">
                            <td class="totals-label">Subtotal</td>
                            <td class="totals-value">{{ number_format($invoice->tax_exclusive_amount, 2) }}</td>
                        </tr>
                        <tr class="totals-row">
                            <td class="totals-label">Total Tax</td>
                            <td class="totals-value">{{ number_format($invoice->tax_inclusive_amount - $invoice->tax_exclusive_amount, 2) }}</td>
                        </tr>
                        <tr class="grand-total-row">
                            <td class="grand-total-label">Payable Total</td>
                            <td class="grand-total-value">{{ number_format($invoice->payable_amount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="footer">
            Generated by Veridex Compliance Engine. This is a computer generated document.
            <br>
            Powered by Veridex E-Invoicing Cloud.
        </div>
    </div>
</body>
</html>
