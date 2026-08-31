<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Inventory Asset QR Labels - A4</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: #111827;
            background: #e5e7eb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #d1d5db;
            background: white;
        }

        .toolbar-left,
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-info {
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
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

        .preview-wrapper {
            display: grid;
            justify-content: center;
            gap: 18px;
            padding: 20px;
        }

        /*
         * A4 printable area:
         * 210 x 297 mm
         * page margin: 8 mm
         *
         * Usable:
         * 194 x 281 mm
         *
         * Grid:
         * 3 columns x 4 rows = 12 labels / page
         * label: 62 x 66 mm
         * gap: 3 mm
         */
        .sheet {
            display: grid;
            grid-template-columns: repeat(3, 62mm);
            grid-template-rows: repeat(4, 66mm);
            gap: 3mm;
            width: 194mm;
            min-height: 281mm;
            align-content: start;
            justify-content: center;
            padding: 4mm 1mm;
            background: white;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
        }

        .label {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            width: 62mm;
            height: 66mm;
            overflow: hidden;
            border: 0.35mm dashed #9ca3af;
            border-radius: 2mm;
            padding: 4mm 3mm 3mm;
            background: white;
        }

        .asset-code {
            width: 100%;
            overflow: hidden;
            color: #111827;
            font-size: 12pt;
            font-weight: 800;
            line-height: 1.05;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .item-name {
            width: 100%;
            min-height: 4mm;
            margin-top: 1.2mm;
            overflow: hidden;
            color: #4b5563;
            font-size: 7.5pt;
            font-weight: 600;
            line-height: 1.1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .qr {
            display: block;
            width: 31mm;
            height: 31mm;
            margin: 2.5mm auto 0;
            object-fit: contain;
        }

        .qr-value {
            width: 100%;
            margin-top: 2mm;
            overflow: hidden;
            font-family: "Courier New", Courier, monospace;
            font-size: 8.2pt;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-footer {
            width: 100%;
            margin-top: auto;
            padding-top: 1.5mm;
            border-top: 0.2mm solid #e5e7eb;
            color: #6b7280;
            font-size: 5.8pt;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.2mm;
        }

        .empty {
            margin: 20px;
            padding: 30px;
            border: 1px dashed #9ca3af;
            background: white;
            text-align: center;
        }

        @media print {
            html,
            body {
                width: 210mm;
                min-height: 297mm;
                background: white;
            }

            .toolbar,
            .empty {
                display: none !important;
            }

            .preview-wrapper {
                display: block;
                padding: 0;
            }

            .sheet {
                width: 194mm;
                min-height: 281mm;
                margin: 0;
                padding: 4mm 1mm;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }

            .sheet:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .label {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="{{ route('admin.inventory.assets.index') }}">
                &larr; Back to Assets
            </a>

            <span class="toolbar-info">
                {{ $assets->count() }} label
                &middot;
                12 label / A4
                &middot;
                {{ (int) ceil($assets->count() / 12) }} page
            </span>
        </div>

        <div class="toolbar-right">
            <button type="button" onclick="window.print()">
                Print A4 QR Sheet
            </button>
        </div>
    </div>

    @if ($assets->isEmpty())
        <div class="empty">
            Tidak ada asset untuk dicetak.
        </div>
    @else
        <div class="preview-wrapper">
            @foreach ($assets->chunk(12) as $pageAssets)
                <section class="sheet">
                    @foreach ($pageAssets as $asset)
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

                            <div class="label-footer">
                                Inventory Asset
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</body>
</html>
