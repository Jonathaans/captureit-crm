<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <title>{{ $workOrder->work_order_number }}</title>

    <style>
        @page {
            margin: 18mm 16mm 18mm 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            line-height: 1.45;
        }

        .header-table,
        .meta-table,
        .items-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: .4px;
        }

        .doc-title {
            text-align: right;
            font-size: 18px;
            font-weight: 700;
        }

        .doc-number {
            text-align: right;
            margin-top: 3px;
            color: #4b5563;
        }

        .divider {
            border-top: 2px solid #111827;
            margin: 12px 0 14px;
        }

        .section-title {
            margin: 16px 0 7px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
        }

        .meta-table td {
            width: 50%;
            padding: 5px 7px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .label {
            color: #6b7280;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .value {
            margin-top: 2px;
            font-weight: 700;
        }

        .items-table th {
            border: 1px solid #111827;
            background: #f3f4f6;
            padding: 7px;
            text-align: left;
            font-size: 9px;
        }

        .items-table td {
            border: 1px solid #d1d5db;
            padding: 8px 7px;
        }

        .items-table .number {
            width: 34px;
            text-align: center;
        }

        .notes {
            min-height: 75px;
            border: 1px solid #d1d5db;
            padding: 9px;
            white-space: pre-wrap;
        }

        .signature-table {
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .signature-table td {
            width: 33.333%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 10px;
        }

        .signature-role {
            font-weight: 700;
            font-size: 9px;
        }

        .signature-space {
            height: 64px;
        }

        .signature-line {
            border-top: 1px solid #111827;
            padding-top: 4px;
            font-weight: 700;
            min-height: 20px;
        }

        .footer {
            margin-top: 24px;
            border-top: 1px solid #d1d5db;
            padding-top: 6px;
            color: #6b7280;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="brand">
                    {{ strtoupper((string) ($workOrder->business_unit ?: 'VARBEL CORPS')) }}
                </div>

                <div style="margin-top:4px;color:#6b7280;">
                    Event & Operational Document
                </div>
            </td>

            <td>
                <div class="doc-title">
                    SURAT PERINTAH KERJA
                </div>

                <div class="doc-number">
                    {{ $workOrder->work_order_number }}
                </div>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="label">Invoice</div>
                <div class="value">{{ $workOrder->invoice_number ?: '-' }}</div>
            </td>

            <td>
                <div class="label">Project Code</div>
                <div class="value">{{ $workOrder->project_code ?: '-' }}</div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="label">Customer</div>
                <div class="value">{{ $workOrder->customer_name ?: '-' }}</div>
            </td>

            <td>
                <div class="label">Sales</div>
                <div class="value">{{ $workOrder->sales_person_name ?: '-' }}</div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="label">Event Date</div>
                <div class="value">{{ $workOrder->event_date?->format('d M Y') ?: '-' }}</div>
            </td>

            <td>
                <div class="label">Location</div>
                <div class="value">{{ $workOrder->location ?: '-' }}</div>
            </td>
        </tr>

        <tr>
            <td colspan="2">
                <div class="label">Project / Event</div>
                <div class="value">{{ $workOrder->project_name ?: '-' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">
        Product / Service
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="number">No.</th>
                <th>Product / Service Name</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($workOrder->items as $index => $item)
                <tr>
                    <td class="number">
                        {{ $index + 1 }}
                    </td>

                    <td>
                        {{ $item->name }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="number">-</td>
                    <td>-</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">
        Notes / Operational Instruction
    </div>

    <div class="notes">{{ $workOrder->notes ?: '-' }}</div>

    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-role">Admin Sales</div>
                <div class="signature-space"></div>
                <div class="signature-line">
                    {{ $workOrder->admin_sales_name ?: '' }}
                </div>
            </td>

            <td>
                <div class="signature-role">Sales</div>
                <div class="signature-space"></div>
                <div class="signature-line">
                    {{ $workOrder->sales_name ?: '' }}
                </div>
            </td>

            <td>
                <div class="signature-role">Operational</div>
                <div class="signature-space"></div>
                <div class="signature-line">
                    {{ $workOrder->operational_name ?: '' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $workOrder->work_order_number }}
        | Invoice {{ $workOrder->invoice_number ?: '-' }}
        | Generated from CRM
    </div>
</body>
</html>
