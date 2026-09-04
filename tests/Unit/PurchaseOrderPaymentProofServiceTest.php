<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Webkul\Invoice\Services\PurchaseOrderPaymentProofService;

uses(TestCase::class);

it('stores only a valid purchase order payment PDF in private storage', function (): void {
    Storage::fake('local');

    $temporary = tempnam(sys_get_temp_dir(), 'po-proof-');
    file_put_contents(
        $temporary,
        "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"
    );

    try {
        $upload = new UploadedFile(
            $temporary,
            'bukti-transfer.pdf',
            'application/pdf',
            null,
            true
        );

        $path = app(PurchaseOrderPaymentProofService::class)->store($upload, 42);

        Storage::disk('local')->assertExists($path);

        expect($path)
            ->toStartWith('purchase-orders/payment-proofs/')
            ->toEndWith('.pdf');
    } finally {
        @unlink($temporary);
    }
});
