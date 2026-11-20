<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Invoice - {{ $invoice->number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 210mm;
            height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            background: white;
        }
        .header {
            border-bottom: 2px solid #000;
            margin-bottom: 20mm;
            padding-bottom: 10mm;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10mm;
        }
        .company-info h1 {
            font-size: 24px;
            margin-bottom: 5mm;
        }
        .company-info p {
            font-size: 11px;
            margin-bottom: 2mm;
        }
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
        }
        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            color: #d9534f;
            margin-top: 5mm;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15mm;
            font-size: 11px;
        }
        .info-block {
            flex: 1;
        }
        .info-block-title {
            font-weight: bold;
            margin-bottom: 3mm;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2mm;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2mm;
        }
        .info-row-label {
            font-weight: 600;
            width: 40%;
        }
        .info-row-value {
            width: 60%;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10mm;
            font-size: 11px;
        }
        table thead {
            background-color: #f5f5f5;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
        }
        table thead th {
            padding: 5mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        table tbody td {
            padding: 4mm;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .summary-section {
            width: 50%;
            margin-left: auto;
            font-size: 11px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 3mm 5mm;
            border-bottom: 1px solid #ddd;
        }
        .summary-row.total {
            background-color: #f5f5f5;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 4mm 5mm;
        }
        .summary-label {
            font-weight: 600;
        }
        .summary-value {
            text-align: right;
        }
        .notes-section {
            margin-top: 10mm;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 5mm;
        }
        .footer {
            margin-top: 15mm;
            border-top: 1px solid #ddd;
            padding-top: 5mm;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
        .page-break {
            page-break-after: always;
            margin-bottom: 20mm;
        }
        .badge {
            display: inline-block;
            padding: 1mm 3mm;
            background-color: #f0f0f0;
            border-radius: 2px;
            font-size: 9px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="company-info">
                    <h1>{{ $company?->name ?? config('app.name', 'DMS') }}</h1>
                    <p><strong>Delivery Invoice</strong></p>
                </div>
                <div>
                    <div class="invoice-title">DELIVERY INVOICE</div>
                    <div class="invoice-number">#{{ $invoice->number }}</div>
                    <div style="font-size: 11px; margin-top: 3mm;">
                        Date: {{ $invoice->invoice_date->format('d-M-Y') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="info-section">
            <div class="info-block">
                <div class="info-block-title">BILL TO</div>
                <div class="info-row">
                    <span class="info-row-label">Retailer:</span>
                    <span class="info-row-value"><strong>{{ $invoice->retailer->name ?? 'N/A' }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Address:</span>
                    <span class="info-row-value">{{ $invoice->retailer->address ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Phone:</span>
                    <span class="info-row-value">{{ $invoice->retailer->phone ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="info-block">
                <div class="info-block-title">INVOICE DETAILS</div>
                <div class="info-row">
                    <span class="info-row-label">Sales Order:</span>
                    <span class="info-row-value"><strong>{{ $invoice->salesOrder->number }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Delivery Note:</span>
                    <span class="info-row-value"><strong>{{ $invoice->deliveryNote->number }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Status:</span>
                    <span class="info-row-value">
                        <span class="badge">{{ ucfirst($invoice->status) }}</span>
                    </span>
                </div>
            </div>

            <div class="info-block">
                <div class="info-block-title">WAREHOUSE</div>
                <div class="info-row">
                    <span class="info-row-label">Name:</span>
                    <span class="info-row-value"><strong>{{ $invoice->salesOrder->warehouse->name ?? 'N/A' }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-row-label">Location:</span>
                    <span class="info-row-value">{{ $invoice->salesOrder->warehouse->address ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table>
            <thead>
                <tr>
                    <th width="5%">S.No</th>
                    <th width="35%">Product Name / Batch</th>
                    <th width="15%" class="text-right">Quantity</th>
                    <th width="15%" class="text-right">Unit Price</th>
                    <th width="15%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $sn = 1;
                    $totalQty = 0;
                    $totalAmount = 0;
                @endphp
                @forelse($invoice->items as $item)
                    @php
                        $totalQty += $item->quantity;
                        $totalAmount += $item->total_price;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $sn++ }}</td>
                        <td>
                            <strong>{{ $item->product->name ?? 'N/A' }}</strong>
                            @if($item->batch)
                                <br>
                                <small>Batch: {{ $item->batch->batch_no }}</small>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->quantity, 3) }}</td>
                        <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right"><strong>{{ number_format($item->total_price, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">No items</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-section">
            <div class="summary-row">
                <span class="summary-label">Subtotal:</span>
                <span class="summary-value">{{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Discount:</span>
                <span class="summary-value">{{ number_format($invoice->discount, 2) }}</span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">GRAND TOTAL:</span>
                <span class="summary-value">{{ number_format($invoice->grand_total, 2) }}</span>
            </div>
        </div>

        <!-- Notes -->
        <div class="notes-section">
            <strong>Notes:</strong>
            <p>This is a delivery invoice for partial or complete delivery of the sales order. Each delivery generates a separate invoice for tracking purposes.</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>Generated on: {{ now()->format('d-M-Y H:i:s') }}</p>
        </div>
    </div>
</body>
</html>
