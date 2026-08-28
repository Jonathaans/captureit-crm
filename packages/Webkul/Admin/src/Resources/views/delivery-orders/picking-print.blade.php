<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>
        Picking List - {{ $deliveryOrder->delivery_order_number }}
    </title>

    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.35;
        }

        .header,
        .meta,
        .items,
        .signatures {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            border: 0;
            vertical-align: top;
        }

        .title {
            font-size: 21px;
            font-weight: 800;
        }

        .number {
            margin-top: 2mm;
            font-size: 12px;
            font-weight: 700;
        }

        .right {
            text-align: right;
        }

        .rule {
            margin: 4mm 0;
            border-top: 1.5px solid #111827;
        }

        .meta td {
            width: 25%;
            padding: 1.8mm 2mm 1.8mm 0;
            border: 0;
            vertical-align: top;
        }

        .label {
            color: #6b7280;
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .value {
            margin-top: 0.5mm;
            font-size: 9px;
            font-weight: 600;
        }

        .items {
            margin-top: 4mm;
        }

        .items thead {
            display: table-header-group;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .items th,
        .items td {
            border: 0.6px solid #d1d5db;
            padding: 1.8mm 1.5mm;
            vertical-align: top;
        }

        .items th {
            background: #f3f4f6;
            font-size: 7.2px;
            text-transform: uppercase;
        }

        .center {
            text-align: center;
        }

        .status {
            font-weight: 700;
            text-transform: uppercase;
        }

        .section-title {
            margin-top: 6mm;
            padding-bottom: 1.5mm;
            border-bottom: 0.7px solid #9ca3af;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .signatures {
            margin-top: 4mm;
            page-break-inside: avoid;
        }

        .signatures td {
            width: 33.333%;
            border: 0;
            padding: 0 4mm;
            text-align: center;
            vertical-align: top;
        }

        .sign-space {
            height: 28mm;
        }

        .sign-line {
            border-top: 0.7px solid #111827;
            padding-top: 1.5mm;
            font-weight: 700;
        }

        .muted {
            color: #6b7280;
            font-size: 7px;
        }
    </style>
</head>

<body>
    <table class="header">
        <tr>
            <td>
                <div class="title">
                    INTERNAL PICKING LIST
                </div>

                <div class="number">
                    {{ $deliveryOrder->delivery_order_number }}
                </div>
            </td>

            <td class="right">
                <div>
                    Project:
                    <strong>{{ $deliveryOrder->project_code ?: '-' }}</strong>
                </div>

                <div style="margin-top: 1mm;">
                    Status:
                    <strong>{{ strtoupper($deliveryOrder->status ?: '-') }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Project Name</div>
                <div class="value">
                    {{ $deliveryOrder->project_name ?: '-' }}
                </div>
            </td>

            <td>
                <div class="label">Customer</div>
                <div class="value">
                    {{ $deliveryOrder->customer_name ?: '-' }}
                </div>
            </td>

            <td>
                <div class="label">Event Date</div>
                <div class="value">
                    {{
                        $deliveryOrder->event_date
                            ? $deliveryOrder->event_date->format('d M Y')
                            : '-'
                    }}
                </div>
            </td>

            <td>
                <div class="label">Location</div>
                <div class="value">
                    {{ $deliveryOrder->location ?: '-' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">
        Actual Inventory
    </div>

    <table class="items">
        <thead>
            <tr>
                <th class="center" style="width: 5%;">No</th>
                <th style="width: 18%;">Equipment</th>
                <th style="width: 20%;">Inventory Item</th>
                <th style="width: 18%;">Actual Asset</th>
                <th class="center" style="width: 9%;">Qty</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 20%;">Trace</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($allocations as $allocation)
                <tr>
                    <td class="center">
                        {{ $loop->iteration }}
                    </td>

                    <td>
                        <strong>
                            {{ $allocation->deliveryOrderItem?->name ?: '-' }}
                        </strong>

                        @if ($allocation->deliveryOrderItem?->description)
                            <div class="muted">
                                {{ $allocation->deliveryOrderItem->description }}
                            </div>
                        @endif
                    </td>

                    <td>
                        <strong>
                            {{ $allocation->inventoryItem?->code ?: '-' }}
                        </strong>

                        <div class="muted">
                            {{ $allocation->inventoryItem?->name ?: '-' }}
                        </div>
                    </td>

                    <td>
                        @if ($allocation->tracking_type === 'serialized')
                            <strong>
                                {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                            </strong>

                            @if ($allocation->inventoryAsset?->barcode_value)
                                <div class="muted">
                                    Barcode:
                                    {{ $allocation->inventoryAsset->barcode_value }}
                                </div>
                            @endif
                        @else
                            Quantity Stock
                        @endif
                    </td>

                    <td class="center">
                        {{
                            rtrim(
                                rtrim(
                                    number_format(
                                        (float) $allocation->quantity,
                                        2,
                                        '.',
                                        ''
                                    ),
                                    '0'
                                ),
                                '.'
                            )
                        }}
                        {{ $allocation->inventoryItem?->unit ?: '-' }}
                    </td>

                    <td class="status">
                        {{ str_replace('_', ' ', $allocation->status) }}
                    </td>

                    <td>
                        @if ($allocation->picked_at)
                            <div>
                                Picked:
                                {{ $allocation->picked_at->format('d M Y H:i') }}
                            </div>
                        @endif

                        @if ($allocation->pickedBy)
                            <div class="muted">
                                {{ $allocation->pickedBy->name }}
                            </div>
                        @endif

                        @if ($allocation->out_at)
                            <div style="margin-top: 1mm;">
                                OUT:
                                {{ $allocation->out_at->format('d M Y H:i') }}
                            </div>
                        @endif

                        @if ($allocation->outBy)
                            <div class="muted">
                                {{ $allocation->outBy->name }}
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">
                        Belum ada inventory allocation.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">
        Warehouse Sign-Off
    </div>

    <table class="signatures">
        <tr>
            <td>
                <strong>Picked / Prepared By</strong>
                <div class="muted">Staff Warehouse</div>
                <div class="sign-space"></div>
                <div class="sign-line">________________</div>
            </td>

            <td>
                <strong>Approved By</strong>
                <div class="muted">Head Warehouse</div>
                <div class="sign-space"></div>
                <div class="sign-line">________________</div>
            </td>

            <td>
                <strong>Released / Driver</strong>
                <div class="muted">Operational</div>
                <div class="sign-space"></div>
                <div class="sign-line">________________</div>
            </td>
        </tr>
    </table>
</body>
</html>
