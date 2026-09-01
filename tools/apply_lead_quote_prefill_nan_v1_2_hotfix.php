<?php

/*
|--------------------------------------------------------------------------
| Lead -> Quote Prefill NaN V1.2 Hotfix
|--------------------------------------------------------------------------
|
| Symptom:
| Generate Quotation from Lead loads the Lead product correctly, but the Quote
| item row shows:
|
| Amount = IDR NaN
| Total  = IDR NaN
|
| and Save Quote fails validation:
|
| items.item_0.total must be a number
| items.item_0.final_total must be a number
|
| Root cause:
| Lead-prefilled products can arrive without `day`, while the existing Quote
| summary already safely treats missing day as 1. The item row, however, still
| multiplies directly by product.day, producing NaN.
|
| This patch:
| - normalizes Lead product numeric defaults
| - defaults missing day to 1
| - makes row Amount/Final Total arithmetic NaN-safe
| - keeps the existing customized QuoteController and Quote Blade intact
|
| No migration.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

$path =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/quotes/create.blade.php';

if (! is_file($path)) {
    fwrite(
        STDERR,
        "Quote create Blade tidak ditemukan: {$path}\n"
    );

    exit(2);
}

$source = file_get_contents($path);

if ($source === false) {
    fwrite(
        STDERR,
        "Quote create Blade tidak dapat dibaca.\n"
    );

    exit(3);
}

/*
|--------------------------------------------------------------------------
| Preflight: recognize the current customized Quote item implementation.
|--------------------------------------------------------------------------
*/

$requiredMarkers = [
    "products: @json(\$leadProducts ?? [])",
    "fetchLeadProducts(leadId)",
    "getProductBaseTotal(product)",
    "::name=\"`\${inputName}[total]`\"",
    "::name=\"`\${inputName}[final_total]`\"",
];

foreach ($requiredMarkers as $marker) {
    if (! str_contains($source, $marker)) {
        fwrite(
            STDERR,
            "Required Quote marker tidak ditemukan: {$marker}\n"
            ."Patch dihentikan agar tidak mengubah Blade yang salah.\n"
        );

        exit(4);
    }
}

if (
    str_contains(
        $source,
        'LEAD QUOTE PREFILL NAN V1.2'
    )
) {
    echo "[SKIP] Lead -> Quote Prefill NaN V1.2 already installed.\n";
    exit(0);
}

$patched = $source;

/*
|--------------------------------------------------------------------------
| 1. Normalize initial Lead products during component creation.
|--------------------------------------------------------------------------
*/

$oldCreated = <<<'BLADE'
                created() {
                    if(this.products.length <= 0) {
                        this.addProduct();
                    }
                },
BLADE;

$newCreated = <<<'BLADE'
                created() {
                    /*
                     * LEAD QUOTE PREFILL NAN V1.2
                     *
                     * Lead products do not always carry all Quote-only numeric
                     * fields. Normalize them before the item row renders so
                     * day/discount/tax never become undefined -> NaN.
                     */
                    this.products = this.normalizeLeadProducts(this.products);

                    if(this.products.length <= 0) {
                        this.addProduct();
                    }
                },
BLADE;

if (! str_contains($patched, $oldCreated)) {
    fwrite(
        STDERR,
        "Quote component created() block tidak dikenali. File tidak diubah.\n"
    );

    exit(5);
}

$patched = str_replace(
    $oldCreated,
    $newCreated,
    $patched,
    $createdCount
);

if ($createdCount !== 1) {
    fwrite(
        STDERR,
        "Expected 1 created() patch target, found {$createdCount}.\n"
    );

    exit(6);
}

/*
|--------------------------------------------------------------------------
| 2. Add Lead product normalizer before fetchLeadProducts().
|--------------------------------------------------------------------------
*/

$fetchDoc = <<<'BLADE'
                    /**
                     * Fetch and replace items with selected lead products.
BLADE;

$normalizer = <<<'BLADE'
                    /**
                     * Normalize Lead products into the numeric shape expected
                     * by Quote item validation/calculation.
                     *
                     * @param {Array} products
                     *
                     * @returns {Array}
                     */
                    normalizeLeadProducts(products) {
                        return (Array.isArray(products) ? products : []).map((product) => {
                            return {
                                ...product,

                                day:
                                    this.parseDecimal(product?.day) > 0
                                        ? this.parseDecimal(product.day)
                                        : 1,

                                quantity:
                                    this.parseDecimal(product?.quantity) > 0
                                        ? this.parseDecimal(product.quantity)
                                        : 1,

                                price:
                                    this.formatDecimal(product?.price),

                                discount_amount:
                                    this.formatDecimal(product?.discount_amount),

                                tax_amount:
                                    this.formatDecimal(product?.tax_amount),

                                total:
                                    this.formatDecimal(
                                        this.parseDecimal(product?.price)
                                        * (
                                            this.parseDecimal(product?.quantity) > 0
                                                ? this.parseDecimal(product.quantity)
                                                : 1
                                        )
                                        * (
                                            this.parseDecimal(product?.day) > 0
                                                ? this.parseDecimal(product.day)
                                                : 1
                                        )
                                    ),
                            };
                        });
                    },

BLADE;

if (! str_contains($patched, $fetchDoc)) {
    fwrite(
        STDERR,
        "fetchLeadProducts documentation marker tidak ditemukan.\n"
    );

    exit(7);
}

$patched = str_replace(
    $fetchDoc,
    $normalizer.$fetchDoc,
    $patched,
    $normalizerCount
);

if ($normalizerCount !== 1) {
    fwrite(
        STDERR,
        "Expected 1 normalizer insertion target, found {$normalizerCount}.\n"
    );

    exit(8);
}

/*
|--------------------------------------------------------------------------
| 3. Normalize products returned when Lead selection changes.
|--------------------------------------------------------------------------
*/

$oldFetchLine =
    '                                const leadProducts = response.data?.data ?? [];';

$newFetchLine =
    '                                const leadProducts = this.normalizeLeadProducts(response.data?.data ?? []);';

if (! str_contains($patched, $oldFetchLine)) {
    fwrite(
        STDERR,
        "Lead product fetch assignment tidak ditemukan.\n"
    );

    exit(9);
}

$patched = str_replace(
    $oldFetchLine,
    $newFetchLine,
    $patched,
    $fetchCount
);

if ($fetchCount !== 1) {
    fwrite(
        STDERR,
        "Expected 1 fetch product normalization target, found {$fetchCount}.\n"
    );

    exit(10);
}

/*
|--------------------------------------------------------------------------
| 4. Make item-row Amount calculation safe even for legacy/stale data.
|--------------------------------------------------------------------------
*/

$oldBase =
    'product.price * product.quantity * product.day';

$newBase =
    'product.price * product.quantity * (product.day || 1)';

$baseCount =
    substr_count(
        $patched,
        $oldBase
    );

if ($baseCount < 2) {
    fwrite(
        STDERR,
        "Expected at least 2 item base-total expressions, found {$baseCount}.\n"
    );

    exit(11);
}

$patched = str_replace(
    $oldBase,
    $newBase,
    $patched
);

/*
|--------------------------------------------------------------------------
| 5. Make Final Total tax/discount parse safe.
|--------------------------------------------------------------------------
*/

$oldFinal =
    'parseFloat(product.price * product.quantity * (product.day || 1)) + parseFloat(product.tax_amount) - parseFloat(product.discount_amount)';

$newFinal =
    'parseFloat(product.price * product.quantity * (product.day || 1)) + parseFloat(product.tax_amount || 0) - parseFloat(product.discount_amount || 0)';

$finalCount =
    substr_count(
        $patched,
        $oldFinal
    );

if ($finalCount < 2) {
    fwrite(
        STDERR,
        "Expected at least 2 final-total expressions after base patch, found {$finalCount}.\n"
    );

    exit(12);
}

$patched = str_replace(
    $oldFinal,
    $newFinal,
    $patched
);

/*
|--------------------------------------------------------------------------
| Validate result BEFORE write.
|--------------------------------------------------------------------------
*/

$validationMarkers = [
    'LEAD QUOTE PREFILL NAN V1.2',
    'normalizeLeadProducts(products)',
    'this.normalizeLeadProducts(response.data?.data ?? [])',
    'product.price * product.quantity * (product.day || 1)',
    'parseFloat(product.tax_amount || 0)',
    'parseFloat(product.discount_amount || 0)',
];

foreach ($validationMarkers as $marker) {
    if (! str_contains($patched, $marker)) {
        fwrite(
            STDERR,
            "Hasil patch gagal validasi: {$marker}\n"
        );

        exit(13);
    }
}

$backup =
    $path
    .'.before-lead-quote-prefill-nan-v1-2.bak';

if (! is_file($backup)) {
    if (! copy($path, $backup)) {
        fwrite(
            STDERR,
            "Gagal membuat backup Quote create Blade.\n"
        );

        exit(14);
    }
}

if (
    file_put_contents(
        $path,
        $patched
    ) === false
) {
    fwrite(
        STDERR,
        "Gagal menulis Quote create Blade.\n"
    );

    exit(15);
}

echo "[PASS] Lead product numeric defaults normalized.\n";
echo "[PASS] Missing day now defaults to 1.\n";
echo "[PASS] Quote item Amount calculation is NaN-safe.\n";
echo "[PASS] Quote item Final Total calculation is NaN-safe.\n";
echo "[PASS] Existing QuoteController / pricing workflow preserved.\n";
echo "[PASS] No migration.\n";
