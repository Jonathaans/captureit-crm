<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <title>
        {{ $invoice->invoice_number }}
    </title>

    <style>
        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, sans-serif;
        }

        body {
            margin: 0;
            padding: 30px;
            font-size: 11px;
            color: #1f2937;
        }

        .header {
            width: 100%;
            margin-bottom: 35px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .company {
            font-size: 20px;
            font-weight: bold;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            text-align: right;
        }

        .invoice-number {
            margin-top: 5px;
            text-align: right;
            color: #6b7280;
        }

        .info-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 5px 0;
        }

        .label {
            color: #6b7280;
            font-size: 9px;
            text-transform: uppercase;
        }

        .value {
            margin-top: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .items th {
            padding: 10px;
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
            text-align: left;
        }

        .items td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary {
            width: 320px;
            margin-left: auto;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .summary td {
            padding: 6px 0;
        }

        .grand-total td {
            border-top: 1px solid #111827;
            padding-top: 10px;
            font-weight: bold;
            font-size: 13px;
        }

        .payment-summary {
            width: 100%;
            margin-top: 35px;
            padding: 15px;
            background: #f9fafb;
        }

        .status-paid {
            color: #15803d;
            font-weight: bold;
        }

        .status-partial {
            color: #a16207;
            font-weight: bold;
        }

        .status-unpaid {
            color: #b91c1c;
            font-weight: bold;
        }

        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            text-align: center;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <div class="company">
                        {{ config('app.name') }}
                    </div>
                </td>

                <td>
                    <div class="invoice-title">
                        INVOICE
                    </div>

                    <div class="invoice-number">
                        {{ $invoice->invoice_number }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Customer + Invoice Info -->
    <table class="info-table">
        <tr>
            <td>
                <div class="label">
                    Bill To
                </div>

                <div class="value">
                    {{ $invoice->person?->name ?? '-' }}
                </div>
            </td>

            <td>
                <div class="label">
                    Issued Date
                </div>

                <div class="value">
                    {{ $invoice->issued_at?->format('d M Y') ?? '-' }}
                </div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="label">
                    Subject
                </div>

                <div class="value">
                    {{ $invoice->subject }}
                </div>
            </td>

            <td>
                <div class="label">
                    Due Date
                </div>

                <div class="value">
                    {{ $invoice->due_at?->format('d M Y') ?? '-' }}
                </div>
            </td>
        </tr>

        @if ($invoice->quote)
            <tr>
                <td>
                    <div class="label">
                        Quote Reference
                    </div>

                    <div class="value">
                        Quote #{{ $invoice->quote->id }}
                    </div>
                </td>

                <td></td>
            </tr>
        @endif
    </table>

    <!-- Items -->
    <table class="items">
        <thead>
            <tr>
                <th>
                    Description
                </th>

                <th class="text-center">
                    Qty
                </th>

                <th class="text-right">
                    Price
                </th>

                <th class="text-right">
                    Total
                </th>
            </tr>
        </thead>

        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->name }}

                        @if ($item->sku)
                            <br>
                            <small>
                                SKU: {{ $item->sku }}
                            </small>
                        @endif
                    </td>

                    <td class="text-center">
                        {{ $item->quantity }}
                    </td>

                    <td class="text-right">
                        Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                    </td>

                    <td class="text-right">
                        Rp {{ number_format((float) $item->total, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Summary -->
    <table class="summary">
        <tr>
            <td>
                Subtotal
            </td>

            <td class="text-right">
                Rp {{ number_format((float) $invoice->sub_total, 0, ',', '.') }}
            </td>
        </tr>

        @if ((float) $invoice->discount_amount > 0)
            <tr>
                <td>
                    Discount
                </td>

                <td class="text-right">
                    - Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                </td>
            </tr>
        @endif

        @if ((float) $invoice->tax_amount > 0)
            <tr>
                <td>
                    Tax
                </td>

                <td class="text-right">
                    Rp {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                </td>
            </tr>
        @endif

        <tr class="grand-total">
            <td>
                Grand Total
            </td>

            <td class="text-right">
                Rp {{ number_format((float) $invoice->grand_total, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <!-- Payment Summary -->
    <table class="payment-summary">
        <tr>
            <td>
                Paid
            </td>

            <td class="text-right">
                Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}
            </td>
        </tr>

        <tr>
            <td>
                Balance Due
            </td>

            <td class="text-right">
                Rp {{ number_format((float) $invoice->balance_due, 0, ',', '.') }}
            </td>
        </tr>

        <tr>
            <td>
                Status
            </td>

            <td class="text-right">
                @if ($invoice->status === 'paid')
                    <span class="status-paid">
                        PAID
                    </span>
                @elseif ($invoice->status === 'partial')
                    <span class="status-partial">
                        PARTIAL
                    </span>
                @else
                    <span class="status-unpaid">
                        UNPAID
                    </span>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $invoice->invoice_number }}
        —
        Generated {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>