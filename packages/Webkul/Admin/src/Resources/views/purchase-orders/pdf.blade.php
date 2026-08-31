<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">

    @php
        /*
         * Purchase Order PDF deliberately mirrors the current Quotation PDF.
         */
        $logoPath = public_path('images/logo-varbel.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo varbel.png');
        }

        $logoData = null;

        if (file_exists($logoPath)) {
            $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));

            $mime = in_array($extension, ['jpg', 'jpeg'], true)
                ? 'image/jpeg'
                : 'image/png';

            $logoData = 'data:'.$mime.';base64,'.base64_encode(
                file_get_contents($logoPath)
            );
        }

        $companyLegalName = config(
            'company.legal_name',
            'PT. VARBEL ANVAYA BERSAUDARA'
        );

        $companyAddressLine1 = config(
            'company.address_line_1',
            'Jl. Tomang Asli No. 22 RT. 005 RW. 002, Jatipulo, Palmerah'
        );

        $companyAddressLine2 = config(
            'company.address_line_2',
            'Kota Administrasi Jakarta Barat, DKI Jakarta'
        );

        $companyPhone = config('company.phone', '081585573616');
        $companyEmail = config('company.email', 'financephoto.360@gmail.com');
        $companyNpwp = config('company.npwp', '1000.0000.1027.6657');

        $invoice = $purchaseOrder->invoice;

        $orderDate = $purchaseOrder->order_date
            ? core()->formatDate($purchaseOrder->order_date, 'd M Y')
            : '-';

        $eventDate = $invoice?->event_date
            ? core()->formatDate($invoice->event_date, 'd M Y')
            : '-';
    @endphp

    <style>
        @page {
            margin: 22px 28px 72px 28px;
        }

        * {
            box-sizing: border-box;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .top-table td {
            vertical-align: middle;
        }

        .logo-cell {
            width: 52%;
            text-align: left;
        }

        .company-cell {
            width: 48%;
            padding-left: 24px;
            text-align: left;
            color: #111827;
        }

        .logo {
            max-width: 170px;
            max-height: 82px;
        }

        .company-name {
            margin: 0 0 3px 0;
            color: #111827;
            font-size: 12px;
            line-height: 1.25;
            font-weight: bold;
        }

        .company-line {
            margin: 1px 0;
            color: #111827;
            font-size: 8px;
            line-height: 1.45;
        }

        .company-label {
            font-weight: bold;
        }

        .header-rule {
            margin-top: 12px;
            border-top: 2px solid #d5aa2a;
        }

        .document-title {
            padding: 18px 0 14px 0;
            text-align: center;
        }

        .document-title h1 {
            margin: 0;
            color: #111827;
            font-size: 24px;
            letter-spacing: 2px;
        }

        .document-number {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10px;
        }

        .status-line {
            margin-top: 4px;
            color: #111827;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .8px;
        }

        .info-table {
            margin-top: 4px;
            margin-bottom: 18px;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
        }

        .left-info {
            padding-right: 22px;
        }

        .right-info {
            padding-left: 22px;
        }

        .section-label {
            margin-bottom: 7px;
            color: #111827;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .vendor-name {
            margin-bottom: 4px;
            color: #111827;
            font-size: 12px;
            font-weight: bold;
        }

        .address-line {
            margin: 1px 0;
            color: #4b5563;
        }

        .project-row {
            margin-bottom: 4px;
        }

        .project-label {
            display: inline-block;
            width: 92px;
            color: #6b7280;
            font-size: 9px;
        }

        .project-value {
            color: #111827;
            font-weight: bold;
        }

        .items-table {
            margin-top: 6px;
            page-break-inside: auto;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table tbody {
            display: table-row-group;
        }

        .items-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .items-table thead th {
            padding: 8px 7px;
            background: #111827;
            color: #ffffff;
            border: 1px solid #111827;
            font-size: 8px;
            text-align: center;
            text-transform: uppercase;
        }

        .items-table tbody td {
            padding: 9px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table .no {
            width: 6%;
            text-align: center;
        }

        .items-table .package {
            width: 21%;
        }

        .items-table .description {
            width: 27%;
        }

        .items-table .qty,
        .items-table .unit {
            width: 8%;
            text-align: center;
        }

        .items-table .price,
        .items-table .total {
            width: 15%;
            text-align: right;
        }

        .item-name {
            color: #111827;
            font-weight: bold;
        }

        .summary-wrap {
            margin-top: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .summary-table {
            width: 42%;
            margin-left: auto;
        }

        .summary-table td {
            padding: 4px 6px;
        }

        .summary-label {
            color: #6b7280;
        }

        .summary-value {
            text-align: right;
            color: #111827;
        }

        .grand-total td {
            padding-top: 8px;
            padding-bottom: 8px;
            border-top: 2px solid #111827;
            color: #111827;
            font-size: 11px;
            font-weight: bold;
        }

        .description-box {
            margin-top: 16px;
            page-break-inside: avoid;
            break-inside: avoid;
            padding: 10px 12px;
            background: #f9fafb;
            border-left: 3px solid #d5aa2a;
        }

        .description-box strong {
            display: block;
            margin-bottom: 4px;
            color: #111827;
        }

        .bottom-table {
            margin-top: 30px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .bottom-table td {
            width: 50%;
            vertical-align: top;
            text-align: center;
        }

        .left-signature {
            padding-right: 25px;
        }

        .right-signature {
            padding-left: 25px;
        }

        .signature-space {
            height: 100px;
        }

        .signature-line {
            width: 180px;
            margin: 0 auto 5px auto;
            border-top: 1px solid #111827;
        }

        .signature-name {
            font-weight: bold;
            color: #111827;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            padding-top: 8px;
            padding-bottom: 4px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 7px;
        }
    </style>
</head>

<body>
    <table class="top-table">
        <tr>
            <td class="logo-cell">
                @if ($logoData)
                    <img class="logo" src="{{ $logoData }}" alt="Logo">
                @endif
            </td>

            <td class="company-cell">
                <div class="company-name">{{ $companyLegalName }}</div>
                <div class="company-line">{{ $companyAddressLine1 }}</div>
                <div class="company-line">{{ $companyAddressLine2 }}</div>
                <div class="company-line"><span class="company-label">Telp:</span> {{ $companyPhone }}</div>
                <div class="company-line"><span class="company-label">Email:</span> {{ $companyEmail }}</div>
                <div class="company-line"><span class="company-label">NPWP:</span> {{ $companyNpwp }}</div>
            </td>
        </tr>
    </table>

    <div class="header-rule"></div>

    <div class="document-title">
        <h1>PURCHASE ORDER</h1>
        <div class="document-number">{{ $purchaseOrder->po_number }}</div>
        <div class="status-line">{{ strtoupper($purchaseOrder->status) }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="left-info">
                <div class="section-label">Vendor To</div>
                <div class="vendor-name">{{ $purchaseOrder->vendor_name }}</div>

                @if ($purchaseOrder->vendor_address)
                    <div class="address-line">{!! nl2br(e($purchaseOrder->vendor_address)) !!}</div>
                @endif

                @if ($purchaseOrder->vendor_phone)
                    <div class="address-line">Telp: {{ $purchaseOrder->vendor_phone }}</div>
                @endif

                @if ($purchaseOrder->vendor_email)
                    <div class="address-line">Email: {{ $purchaseOrder->vendor_email }}</div>
                @endif
            </td>

            <td class="right-info">
                <div class="project-row"><span class="project-label">Date</span><span class="project-value">: {{ $orderDate }}</span></div>
                <div class="project-row"><span class="project-label">Payment Terms</span><span class="project-value">: {{ $purchaseOrder->payment_terms_label }}</span></div>
                <div class="project-row"><span class="project-label">Invoice</span><span class="project-value">: {{ $purchaseOrder->invoice_number ?: '-' }}</span></div>
                <div class="project-row"><span class="project-label">Project Name</span><span class="project-value">: {{ $purchaseOrder->project_name ?: '-' }}</span></div>
                <div class="project-row"><span class="project-label">Project Code</span><span class="project-value">: {{ $purchaseOrder->project_code ?: '-' }}</span></div>
                <div class="project-row"><span class="project-label">Business Unit</span><span class="project-value">: {{ $businessUnitLabel ?: '-' }}</span></div>
                <div class="project-row"><span class="project-label">Event Date</span><span class="project-value">: {{ $eventDate }}</span></div>
                <div class="project-row"><span class="project-label">Location</span><span class="project-value">: {{ $invoice?->location ?: '-' }}</span></div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Product / Service</th>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($purchaseOrder->items as $index => $item)
                <tr>
                    <td class="no">{{ $index + 1 }}</td>
                    <td class="package"><div class="item-name">{{ $item->name }}</div></td>
                    <td class="description">{{ $item->description ?: '-' }}</td>
                    <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                    <td class="unit">{{ $item->unit }}</td>
                    <td class="price">{!! core()->formatBasePrice($item->unit_price, true) !!}</td>
                    <td class="total">{!! core()->formatBasePrice($item->total, true) !!}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding:18px;text-align:center;color:#9ca3af;">No items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-wrap">
        <table class="summary-table">
            <tr>
                <td class="summary-label">Sub Total</td>
                <td class="summary-value">{!! core()->formatBasePrice($purchaseOrder->sub_total, true) !!}</td>
            </tr>

            @if ((float) $purchaseOrder->adjustment_amount !== 0.0)
                <tr>
                    <td class="summary-label">Adjustment</td>
                    <td class="summary-value">{!! core()->formatBasePrice($purchaseOrder->adjustment_amount, true) !!}</td>
                </tr>
            @endif

            <tr class="grand-total">
                <td>GRAND TOTAL</td>
                <td class="summary-value">{!! core()->formatBasePrice($purchaseOrder->grand_total, true) !!}</td>
            </tr>
        </table>
    </div>

    @if ($purchaseOrder->notes)
        <div class="description-box">
            <strong>Notes</strong>
            {!! nl2br(e($purchaseOrder->notes)) !!}
        </div>
    @endif

    <table class="bottom-table">
        <tr>
            <td class="left-signature">
                <div class="section-label">Vendor</div>
                <div class="signature-space"></div>
                <div class="signature-line"></div>
                <div class="signature-name">{{ $purchaseOrder->vendor_name }}</div>
            </td>

            <td class="right-signature">
                <div class="section-label">Approved By</div>
                <div class="signature-space"></div>
                <div class="signature-line"></div>
                <div class="signature-name">Rudy Tinambunan</div>
            </td>
        </tr>
    </table>

    <div class="footer">Member of Rental Indonesia.</div>
</body>
</html>
