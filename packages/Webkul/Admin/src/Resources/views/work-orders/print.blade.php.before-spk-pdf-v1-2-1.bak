<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">

    @php
        /*
         * SPK PDF V1.2
         * Visual language intentionally follows the existing Quotation PDF:
         * - KOP/logo + company identity
         * - gold header rule
         * - centered document title
         * - Bill To + Project Details
         * - dark item table header
         * - fixed footer
         *
         * Financial amounts are intentionally NOT printed.
         */

        $invoice = $workOrder->invoice;
        $quote = $invoice?->quote;
        $person = $invoice?->person;

        /*
         * Logo paths are kept identical to the Quotation PDF implementation.
         */
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
                [
                    'jpg',
                    'jpeg',
                ],
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

        /*
         * Company identity follows Quotation.
         */
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

        /*
         * Billing snapshot.
         * Prefer Invoice, fall back to Quote.
         */
        $billingAddress =
            $invoice?->billing_address
            ?: $quote?->billing_address
            ?: [];

        if (is_string($billingAddress)) {
            $decodedAddress =
                json_decode(
                    $billingAddress,
                    true
                );

            $billingAddress =
                is_array($decodedAddress)
                    ? $decodedAddress
                    : [];
        }

        if (! is_array($billingAddress)) {
            $billingAddress = [];
        }

        $addressLine =
            $billingAddress['address']
            ?? '';

        $cityLine =
            trim(
                implode(
                    ' ',
                    array_filter([
                        $billingAddress['postcode']
                            ?? null,

                        $billingAddress['city']
                            ?? null,
                    ])
                )
            );

        $stateLine =
            $billingAddress['state']
            ?? '';

        $countryLine = '';

        if (! empty(
            $billingAddress['country']
            ?? null
        )) {
            try {
                $countryLine =
                    core()->country_name(
                        $billingAddress['country']
                    );
            } catch (\Throwable) {
                $countryLine =
                    $billingAddress['country'];
            }
        }

        $createdDate =
            $workOrder->created_at
                ? core()->formatDate(
                    $workOrder->created_at,
                    'd M Y'
                )
                : '-';

        $eventDate =
            $workOrder->event_date
                ? core()->formatDate(
                    $workOrder->event_date,
                    'd M Y'
                )
                : '-';

        $invoiceIssuedDate =
            $invoice?->issued_at
                ? core()->formatDate(
                    $invoice->issued_at,
                    'd M Y'
                )
                : '-';

        $quoteNumber =
            $workOrder->quote_number
            ?: $quote?->quote_number
            ?: '-';

        $customerName =
            $workOrder->customer_name
            ?: $person?->name
            ?: '-';

        $salesName =
            $workOrder->sales_person_name
            ?: $invoice?->user?->name
            ?: '-';

        $statusLabel =
            strtoupper(
                (string) (
                    $workOrder->status
                    ?: 'draft'
                )
            );
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

        /*
         * KOP - aligned with Quotation.
         */
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

        /*
         * Document title.
         */
        .document-title {
            padding: 16px 0 13px 0;
            text-align: center;
        }

        .document-title h1 {
            margin: 0;
            color: #111827;
            font-size: 22px;
            letter-spacing: 1.7px;
        }

        .document-number {
            margin-top: 4px;
            color: #6b7280;
            font-size: 10px;
        }

        /*
         * Full project details, matching Quotation style.
         */
        .info-table {
            margin-top: 4px;
            margin-bottom: 17px;
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

        .customer-name {
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
            width: 94px;
            color: #6b7280;
            font-size: 9px;
        }

        .project-value {
            color: #111827;
            font-weight: bold;
        }

        /*
         * Product/Service table.
         * IMPORTANT: product/service NAME ONLY.
         */
        .items-table {
            margin-top: 6px;
            page-break-inside: auto;
        }

        .items-table thead {
            display: table-header-group;
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
            text-align: left;
            text-transform: uppercase;
        }

        .items-table tbody td {
            padding: 9px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .items-table .no {
            width: 8%;
            text-align: center;
        }

        .items-table .product {
            width: 92%;
        }

        .item-name {
            color: #111827;
            font-weight: bold;
        }

        /*
         * Operational Notes, styled like Quotation Notes.
         */
        .description-box {
            margin-top: 16px;
            padding: 11px 13px;
            background: #f9fafb;
            border-left: 3px solid #d5aa2a;
            page-break-inside: avoid;
            break-inside: avoid;
            min-height: 64px;
        }

        .description-box strong {
            display: block;
            margin-bottom: 5px;
            color: #111827;
        }

        .notes-content {
            white-space: pre-wrap;
            color: #374151;
        }

        /*
         * Three signatures.
         *
         * This spacing is deliberately more generous than V1:
         * - 34px separation after notes
         * - 92px signing space
         * - equal 3-column widths
         * - consistent line width and name baseline
         *
         * The entire block is kept on one page.
         */
        .signature-table {
            margin-top: 34px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .signature-table td {
            width: 33.333%;
            padding: 0 16px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-role {
            min-height: 20px;
            color: #111827;
            font-size: 9px;
            font-weight: bold;
        }

        .signature-space {
            height: 92px;
        }

        .signature-line {
            width: 86%;
            max-width: 170px;
            margin: 0 auto 5px auto;
            border-top: 1px solid #111827;
        }

        .signature-name {
            min-height: 18px;
            color: #111827;
            font-size: 9px;
            font-weight: bold;
        }

        .signature-placeholder {
            color: #9ca3af;
            font-weight: normal;
        }

        /*
         * Footer follows Quotation.
         */
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
    <!-- KOP -->
    <table class="top-table">
        <tr>
            <td class="logo-cell">
                @if ($logoData)
                    <img
                        class="logo"
                        src="{{ $logoData }}"
                        alt="Logo"
                    >
                @endif
            </td>

            <td class="company-cell">
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
                    <span class="company-label">Telp:</span>
                    {{ $companyPhone }}
                </div>

                <div class="company-line">
                    <span class="company-label">Email:</span>
                    {{ $companyEmail }}
                </div>

                <div class="company-line">
                    <span class="company-label">NPWP:</span>
                    {{ $companyNpwp }}
                </div>
            </td>
        </tr>
    </table>

    <div class="header-rule"></div>

    <!-- Document Title -->
    <div class="document-title">
        <h1>SURAT PERINTAH KERJA</h1>

        <div class="document-number">
            {{ $workOrder->work_order_number }}
        </div>
    </div>

    <!-- Bill To + Complete Project Details -->
    <table class="info-table">
        <tr>
            <td class="left-info">
                <div class="section-label">
                    Bill To
                </div>

                <div class="customer-name">
                    {{ $customerName }}
                </div>

                @if ($addressLine)
                    <div class="address-line">
                        {{ $addressLine }}
                    </div>
                @endif

                @if ($cityLine)
                    <div class="address-line">
                        {{ $cityLine }}
                    </div>
                @endif

                @if ($stateLine)
                    <div class="address-line">
                        {{ $stateLine }}
                    </div>
                @endif

                @if ($countryLine)
                    <div class="address-line">
                        {{ $countryLine }}
                    </div>
                @endif

                @if (! $addressLine && ! $cityLine && ! $stateLine && ! $countryLine)
                    <div class="address-line">
                        Address not available.
                    </div>
                @endif
            </td>

            <td class="right-info">
                <div class="project-row">
                    <span class="project-label">SPK Date</span>
                    <span class="project-value">: {{ $createdDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Invoice</span>
                    <span class="project-value">: {{ $workOrder->invoice_number ?: '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Invoice Date</span>
                    <span class="project-value">: {{ $invoiceIssuedDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Quote Ref.</span>
                    <span class="project-value">: {{ $quoteNumber }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Name</span>
                    <span class="project-value">: {{ $workOrder->project_name ?: '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Project Code</span>
                    <span class="project-value">: {{ $workOrder->project_code ?: '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Event Date</span>
                    <span class="project-value">: {{ $eventDate }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Location</span>
                    <span class="project-value">: {{ $workOrder->location ?: '-' }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">Sales</span>
                    <span class="project-value">: {{ $salesName }}</span>
                </div>

                <div class="project-row">
                    <span class="project-label">SPK Status</span>
                    <span class="project-value">: {{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- SPK Items -->
    <div class="section-label">
        Product / Service
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th class="product">Product / Service Name</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($workOrder->items as $index => $item)
                <tr>
                    <td class="no">
                        {{ $index + 1 }}
                    </td>

                    <td class="product">
                        <div class="item-name">
                            {{ $item->name }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="no">
                        -
                    </td>

                    <td class="product">
                        -
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Notes -->
    <div class="description-box">
        <strong>
            Notes / Operational Instruction
        </strong>

        <div class="notes-content">{{ $workOrder->notes ?: '-' }}</div>
    </div>

    <!-- Three Signatures -->
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-role">
                    Admin Sales
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    @if ($workOrder->admin_sales_name)
                        {{ $workOrder->admin_sales_name }}
                    @else
                        <span class="signature-placeholder">
                            Name
                        </span>
                    @endif
                </div>
            </td>

            <td>
                <div class="signature-role">
                    Sales
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    @if ($workOrder->sales_name)
                        {{ $workOrder->sales_name }}
                    @else
                        <span class="signature-placeholder">
                            Name
                        </span>
                    @endif
                </div>
            </td>

            <td>
                <div class="signature-role">
                    Operational
                </div>

                <div class="signature-space"></div>

                <div class="signature-line"></div>

                <div class="signature-name">
                    @if ($workOrder->operational_name)
                        {{ $workOrder->operational_name }}
                    @else
                        <span class="signature-placeholder">
                            Name
                        </span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Member of Rental Indonesia.
    </div>
</body>
</html>
