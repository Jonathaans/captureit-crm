<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Inventory Asset QR Labels</title>

    <style>
        @page {
            size: 50mm 30mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            background: #f3f4f6;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #d1d5db;
            background: white;
        }

        .toolbar a,
        .toolbar button {
            border: 1px solid #c79a19;
            border-radius: 6px;
            padding: 8px 12px;
            color: #8a6410;
            background: white;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .toolbar button {
            color: white;
            background: #c79a19;
        }

        .labels {
            display: grid;
            justify-content: center;
            gap: 12px;
            padding: 20px;
        }

        .label {
            width: 50mm;
            height: 30mm;
            overflow: hidden;
            border: 1px dashed #9ca3af;
            padding: 2.2mm 2.5mm;
            background: white;
        }

        .asset-code {
            overflow: hidden;
            font-size: 11pt;
            font-weight: 800;
            line-height: 1.1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-name {
            margin-top: 0.7mm;
            overflow: hidden;
            color: #4b5563;
            font-size: 6.8pt;
            line-height: 1.1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .qr {
            display: block;
            width: 13mm;
            height: 13mm;
            margin: 1.1mm auto 0;
            object-fit: contain;
        }

        .qr-value {
            margin-top: 0.4mm;
            font-family: monospace;
            font-size: 6.8pt;
            font-weight: 700;
            text-align: center;
        }

        .legacy-value {
            margin-top: 0.2mm;
            overflow: hidden;
            color: #6b7280;
            font-size: 5.2pt;
            line-height: 1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty {
            margin: 20px;
            padding: 30px;
            border: 1px dashed #9ca3af;
            background: white;
            text-align: center;
        }

        @media print {
            body {
                background: white;
            }

            .toolbar,
            .empty {
                display: none !important;
            }

            .labels {
                display: block;
                padding: 0;
            }

            .label {
                width: 50mm;
                height: 30mm;
                border: 0;
                page-break-after: always;
                break-after: page;
            }

            .label:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <a href="{{ route('admin.inventory.assets.index') }}">
            &larr; Back to Assets
        </a>

        <button type="button" onclick="window.print()">
            Print Labels
        </button>
    </div>

    @if ($assets->isEmpty())
        <div class="empty">
            Tidak ada asset untuk dicetak.
        </div>
    @else
        <div class="labels">
            @foreach ($assets as $asset)
                <div class="label">
                    <div class="asset-code">
                        {{ $asset->asset_code }}
                    </div>

                    <div class="item-name">
                        {{ $asset->item?->name ?: '-' }}
                    </div>

                    <img
                        src="{{ route(
                            'admin.inventory.assets.qr-labels.svg',
                            $asset->id
                        ) }}"
                        class="qr"
                        alt="QR {{ $asset->qr_value ?: $asset->asset_code }}"
                    >

                    <div class="qr-value">
                        {{ $asset->qr_value ?: $asset->asset_code }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
