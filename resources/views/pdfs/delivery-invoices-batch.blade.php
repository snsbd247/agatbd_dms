<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Invoices - Batch</title>
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
            font-size: 10px;
        }
        table thead {
            background-color: #f5f5f5;
            border-top: 2px solid #000;
            border-bottom: 1px solid #000;
        }
        table thead th {
            padding: 4mm;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        table tbody td {
            padding: 3mm;
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
            font-size: 10px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 2mm 5mm;
            border-bottom: 1px solid #ddd;
        }
        .summary-row.total {
            background-color: #f5f5f5;
            font-weight: bold;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 3mm 5mm;
        }
        .summary-label {
            font-weight: 600;
        }
        .summary-value {
            text-align: right;
        }
        .footer {
            margin-top: 10mm;
            border-top: 1px solid #ddd;
            padding-top: 5mm;
            font-size: 8px;
            text-align: center;
            color: #666;
        }
        .page-break {
            page-break-after: always;
            margin-bottom: 20mm;
            padding-bottom: 20mm;
        }
        .badge {
            display: inline-block;
            padding: 1mm 3mm;
            background-color: #f0f0f0;
            border-radius: 2px;
            font-size: 8px;
            font-weight: bold;
        }
        .mini-header {
            margin-top: 5mm;
            margin-bottom: 8mm;
            border-bottom: 2px solid #000;
            padding-bottom: 5mm;
        }
        .mini-title {
            font-size: 14px;
            font-weight: bold;
        }
        .mini-info {
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            margin-top: 3mm;
        }
    </style>
</head>
<body>
    @foreach($invoices as $invoice)
        <div class="container @if(!$loop->last)page-break @endif">
            <!-- Header -->
            <div class="header">
                <div class="header-top">
                    <div class="company-info">
                        @include('pdfs.partials.company')
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
                </div>
            </div>

            <!-- Items Table -->
            <table>
                <thead>
                    <tr>
                        <th width="5%">S.No</th>
                        <th width="40%">Product Name</th>
                        <th width="15%" class="text-right">Qty</th>
                        <th width="15%" class="text-right">Unit Price</th>
                        <th width="15%" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sn = 1; $totalAmount = 0; @endphp
                    @forelse($invoice->items as $item)
                        @php $totalAmount += $item->total_price; @endphp
                        <tr>
                            <td class="text-center">{{ $sn++ }}</td>
                            <td>
                                <strong>{{ $item->product->name ?? 'N/A' }}</strong>
                                @if($item->batch)
                                    <br><small>Batch: {{ $item->batch->batch_no }}</small>
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

            <!-- Footer -->
            <div class="footer">
                @include('pdfs.partials.footer-note')
            <p>Generated on: {{ now()->format('d-M-Y H:i:s') }}</p>
            </div>
        </div>
    @endforeach
</body>
</html>
