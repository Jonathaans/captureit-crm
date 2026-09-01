<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">

    @php
        /*
         * SPK PDF V1.2.1
         *
         * Layout sengaja kembali ke versi simpel:
         * - detail 2 kolom seperti screenshot user
         * - Project / Event full width
         * - Product / Service hanya nama
         * - Notes
         * - 3 TTD
         *
         * Tambahan baru:
         * - KOP surat mengikuti gaya quotation
         */

        $invoice = $workOrder->invoice;

        $logoPath = public_path('images/logo-varbel.png');

        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/logo varbel.png');
        }

        $logoData = null;

        if (file_exists($logoPath)) {
            $extension = strtolower(
                pathinfo(
                    $logoPath,
                    PATHINFO_EXTENSION
                )
            );

            $mime = in_array(
                $extension,
                ['jpg', 'jpeg'],
                true
            )
                ? 'image/jpeg'
                : 'image/png';

            $logoData =
                'data:'
                .$mime
                .';base64,'
                .base64_encode(
                    file_get_contents(
                        $logoPath
                    )
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

        $companyPhone = config(
            'company.phone',
            '081585573616'
        );

        $companyEmail = config(
            'company.email',
            'financephoto.360@gmail.com'
        );

        $companyNpwp = config(
            'company.npwp',
            '1000.0000.1027.6657'
        );
    @endphp

    <style>
        @page {
            margin: 20px 26px 56px 26px;
        }

        * {
            box-sizing: border-box;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }

        body {
            margin: 0;
            color: #111827;
            font-size: 9px;
            line-height: 1.4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        /* KOP */
        .kop-table td {
            vertical-align: middle;
        }

        .kop-logo {
            width: 45%;
            text-align: left;
        }

        .kop-company {
            width: 55%;
            padding-left: 18px;
            text-align: left;
        }

        .logo {
            max-width: 165px;
            max-height: 72px;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .company-line {
            margin: 1px 0;
            font-size: 7.5px;
            color: #374151;
        }

        .kop-rule {
            margin-top: 10px;
            border-top: 2px solid #d5aa2a;
        }

        /* Title */
        .title-wrap {
            padding: 13px 0 11px 0;
            text-align: center;
        }

        .title {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .number {
            margin-top: 3px;
            color: #6b7280;
            font-size: 9px;
        }

        /* Detail grid exactly simple */
        .detail-table {
            margin-top: 2px;
        }

        .detail-table td {
            width: 50%;
            border: 1px solid #d1d5db;
            padding: 7px 8px;
            vertical-align: top;
        }

        .detail-table .full {
            width: 100%;
        }

        .label {
            color: #6b7280;
            font-size: 7px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .value {
            color: #111827;
            font-size: 9px;
            font-weight: bold;
        }

        .section-title {
            margin: 14px 0 6px 0;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Product table */
        .items-table th {
            padding: 7px;
            border: 1px solid #111827;
            background: #f3f4f6;
            color: #111827;
            font-size: 7px;
            text-align: left;
        }

        .items-table td {
            padding: 8px 7px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .items-table .no {
            width: 8%;
            text-align: center;
        }

        /* Notes */
        .notes-box {
            min-height: 68px;
            border: 1px solid #d1d5db;
            padding: 9px;
            white-space: pre-wrap;
        }

        /* Signature */
        .signature-table {
            margin-top: 30px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-table td {
            width: 33.333%;
            padding: 0 18px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-role {
            font-size: 8px;
            font-weight: bold;
        }

        .signature-space {
            height: 78px;
        }

        .signature-line {
            width: 88%;
            margin: 0 auto 5px auto;
            border-top: 1px solid #111827;
        }

        .signature-name {
            min-height: 15px;
            font-size: 8px;
            font-weight: bold;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            text-align: center;
            color: #9ca3af;
            font-size: 7px;
        }
    </style>
</head>

<body>
    <!-- KOP SURAT -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                @if ($logoData)
                    <img
                        src="{{ $logoData }}"
                        class="logo"
                        alt="Logo"
                    >
                @endif
            </td>

            <td class="kop-company">
                <div class="company-name">
                    {{ $companyLegalName }}
                </div>

                <div class="company-line">
                    {{ $companyAddressLine1 }}
                </div>

                <div class="company-line">
                    {{ $companyAddressLine2 }}
                </div>

                <div class="company-line">
                    Telp: {{ $companyPhone }}
                </div>

                <div class="company-line">
                    Email: {{ $companyEmail }}
                </div>

                <div class="company-line">
                    NPWP: {{ $companyNpwp }}
                </div>
            </td>
        </tr>
    </table>

    <div class="kop-rule"></div>

    <!-- TITLE -->
    <div class="title-wrap">
        <div class="title">
            SURAT PERINTAH KERJA
        </div>

        <div class="number">
            {{ $workOrder->work_order_number }}
        </div>
    </div>

    <!-- DETAIL SIMPLE GRID -->
    <table class="detail-table">
        <tr>
            <td>
                <div class="label">
                    Invoice
                </div>

                <div class="value">
                    {{ $workOrder->invoice_number ?: '-' }}
                </div>
            </td>

            <td>
                <div class="label">
                    Project Code
                </div>

                <div class="value">
                    {{ $workOrder->project_code ?: '-' }}
                </div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="label">
                    Customer
                </div>

                <div class="value">
                    {{ $workOrder->customer_name ?: '-' }}
                </div>
            </td>

            <td>
                <div class="label">
                    Sales
                </div>

                <div class="value">
                    {{ $workOrder->sales_person_name ?: '-' }}
                </div>
            </td>
        </tr>

        <tr>
            <td>
                <div class="label">
                    Event Date
                </div>

                <div class="value">
                    {{ $workOrder->event_date?->format('d M Y') ?: '-' }}
                </div>
            </td>

            <td>
                <div class="label">
                    Location
                </div>

                <div class="value">
                    {{ $workOrder->location ?: '-' }}
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="2" class="full">
                <div class="label">
                    Project / Event
                </div>

                <div class="value">
                    {{ $workOrder->project_name ?: '-' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- PRODUCT -->
    <div class="section-title">
        Product / Service
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="no">
                    No.
                </th>

                <th>
                    Product / Service Name
                </th>
            </tr>
        </thead>

        <tbody>
            @forelse ($workOrder->items as $index => $item)
                <tr>
                    <td class="no">
                        {{ $index + 1 }}
                    </td>

                    <td>
                        {{ $item->name }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="no">-</td>
                    <td>-</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- NOTES -->
    <div class="section-title">
        Notes / Operational Instruction
    </div>

    <div class="notes-box">{{ $workOrder->notes ?: '-' }}</div>

    <!-- SIGNATURES -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-role">
                    Admin Sales
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    {{ $workOrder->admin_sales_name ?: '' }}
                </div>
            </td>

            <td>
                <div class="signature-role">
                    Sales
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    {{ $workOrder->sales_name ?: '' }}
                </div>
            </td>

            <td>
                <div class="signature-role">
                    Operational
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    {{ $workOrder->operational_name ?: '' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Member of Rental Indonesia.
    </div>
</body>
</html>
