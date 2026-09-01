<?php

/*
|--------------------------------------------------------------------------
| Quote Sales Owner Role Filter V1.2
|--------------------------------------------------------------------------
|
| Fixes V1 error:
| "block Quote attributes expired_at + user_id tidak ditemukan secara unik."
|
| V1.1 does NOT use one large regex for Blade components.
| It scans every <x-admin::attributes ... /> block by string position, then
| selects the block that actually contains user_id.
|
| Current customized Quote files are patched in-place with backups.
|
*/

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path): void
{
    $backup = $path.'.before-sales-owner-role-filter-v1-1.bak';

    if (! is_file($backup)) {
        copy($path, $backup);
    }
}

function recursivePhpFiles(string $root): array
{
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower($file->getFilename()),
                '.php'
            )
        ) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function findQuoteController(string $root): string
{
    $matches = [];

    foreach (recursivePhpFiles($root) as $path) {
        $source = file_get_contents($path);

        if (
            $source !== false
            && str_contains($source, 'class QuoteController')
            && str_contains($source, 'function create')
            && str_contains($source, 'function update')
        ) {
            $matches[] = $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "QuoteController: expected 1 file, found "
                .count($matches)
                .".\n"
        );

        foreach ($matches as $match) {
            fwrite(STDERR, " - {$match}\n");
        }

        exit(10);
    }

    return $matches[0];
}

/**
 * Return all self-closing x-admin::attributes blocks with offsets.
 */
function attributeBlocks(string $source): array
{
    $blocks = [];
    $offset = 0;
    $needle = '<x-admin::attributes';

    while (true) {
        $start = strpos(
            $source,
            $needle,
            $offset
        );

        if ($start === false) {
            break;
        }

        $end = strpos(
            $source,
            '/>',
            $start
        );

        if ($end === false) {
            break;
        }

        $end += 2;

        $blocks[] = [
            'start' => $start,
            'length' => $end - $start,
            'text' => substr(
                $source,
                $start,
                $end - $start
            ),
        ];

        $offset = $end;
    }

    return $blocks;
}

/**
 * Remove user_id from the custom-attribute code selection while preserving
 * all other Quote attributes in the same component.
 */
function removeUserIdFromAttributeBlock(
    string $block
): ?string {
    /*
     * Case 1:
     * ['code', 'IN', ['expired_at', 'user_id']]
     * or any other list that includes user_id.
     */
    $pattern =
        "/(\\['code'\\s*,\\s*'IN'\\s*,\\s*\\[)(.*?)(\\]\\s*\\])/s";

    if (
        preg_match(
            $pattern,
            $block,
            $match
        ) === 1
    ) {
        $inside = $match[2];

        preg_match_all(
            "/'([^']+)'/",
            $inside,
            $codeMatches
        );

        $codes = $codeMatches[1] ?? [];

        if (in_array('user_id', $codes, true)) {
            $remaining = array_values(
                array_filter(
                    $codes,
                    fn ($code) => $code !== 'user_id'
                )
            );

            if ($remaining) {
                $replacement =
                    $match[1]
                    .implode(
                        ', ',
                        array_map(
                            fn ($code) => "'".$code."'",
                            $remaining
                        )
                    )
                    .$match[3];

                return str_replace(
                    $match[0],
                    $replacement,
                    $block
                );
            }

            /*
             * If this x-admin::attributes component is ONLY user_id, remove the
             * whole component. Caller interprets empty string as removal.
             */
            return '';
        }
    }

    /*
     * Case 2:
     * ['code', '=', 'user_id']
     * ['code', '==', 'user_id']
     */
    if (
        preg_match(
            "/\\['code'\\s*,\\s*'(?:=|==)'\\s*,\\s*'user_id'\\]/",
            $block
        ) === 1
    ) {
        return '';
    }

    /*
     * Case 3:
     * ['code' => 'user_id'] or similar customized associative filtering.
     * Only remove whole component when user_id is the only obvious code.
     */
    if (
        preg_match(
            "/['\"]code['\"]\\s*=>\\s*['\"]user_id['\"]/",
            $block
        ) === 1
    ) {
        return '';
    }

    return null;
}

function salesOwnerSelect(
    bool $isEdit,
    string $marker
): string {
    $current =
        $isEdit
            ? '$quote->user_id'
            : 'null';

    $isEditPhp =
        $isEdit
            ? 'true'
            : 'false';

    $template = <<<'BLADE'

                            <!-- __MARKER__ -->
                            <?php
                                $quoteSalesOwnerCurrentId = old(
                                    'user_id',
                                    __CURRENT__
                                );

                                $quoteSalesOwners = app(
                                    \Webkul\Admin\Services\QuoteSalesOwnerService::class
                                )->options(
                                    __IS_EDIT__
                                        ? (int) ($quoteSalesOwnerCurrentId ?: 0)
                                        : null
                                );
                            ?>

                            <x-admin::form.control-group class="w-full">
                                <x-admin::form.control-group.label class="required">
                                    Sales Owner
                                </x-admin::form.control-group.label>

                                <select
                                    name="user_id"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-800 dark:bg-gray-950"
                                    required
                                >
                                    <option value="">
                                        Select Sales Owner
                                    </option>

                                    <?php foreach ($quoteSalesOwners as $salesOwner): ?>
                                        <option
                                            value="{{ $salesOwner->id }}"
                                            {{ (string) $quoteSalesOwnerCurrentId === (string) $salesOwner->id ? 'selected' : '' }}
                                        >
                                            {{ $salesOwner->name }}
                                            ({{ $salesOwner->role_name ?: 'Current Owner' }})
                                            {{ $salesOwner->is_legacy_current ? ' - Current Owner' : '' }}
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <x-admin::form.control-group.error
                                    control-name="user_id"
                                />
                            </x-admin::form.control-group>
BLADE;

    return str_replace(
        [
            '__MARKER__',
            '__CURRENT__',
            '__IS_EDIT__',
        ],
        [
            $marker,
            $current,
            $isEditPhp,
        ],
        $template
    );
}

function patchQuoteBlade(
    string $path,
    bool $isEdit
): void {
    if (! is_file($path)) {
        fwrite(
            STDERR,
            "Quote Blade tidak ditemukan: {$path}\n"
        );

        exit(11);
    }

    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(
            STDERR,
            "Quote Blade tidak dapat dibaca: {$path}\n"
        );

        exit(12);
    }

    $marker =
        $isEdit
            ? 'QUOTE SALES OWNER ROLE FILTER EDIT V1.2'
            : 'QUOTE SALES OWNER ROLE FILTER CREATE V1.2';

    if (str_contains($source, $marker)) {
        echo "[SKIP] {$marker} sudah ada.\n";
        return;
    }

    $candidates = [];

    foreach (attributeBlocks($source) as $block) {
        if (
            str_contains(
                $block['text'],
                'user_id'
            )
        ) {
            $candidates[] = $block;
        }
    }

    if (count($candidates) !== 1) {
        fwrite(
            STDERR,
            basename($path)
                .": ditemukan "
                .count($candidates)
                ." x-admin::attributes block yang mengandung user_id.\n"
        );

        foreach ($candidates as $index => $candidate) {
            $oneLine = preg_replace(
                '/\s+/',
                ' ',
                trim($candidate['text'])
            );

            fwrite(
                STDERR,
                " [".($index + 1)."] "
                .substr($oneLine, 0, 320)
                ."\n"
            );
        }

        fwrite(
            STDERR,
            "Patch dihentikan agar tidak mengubah field yang salah.\n"
        );

        exit(13);
    }

    $candidate = $candidates[0];

    $replacementAttribute =
        removeUserIdFromAttributeBlock(
            $candidate['text']
        );

    if ($replacementAttribute === null) {
        $oneLine = preg_replace(
            '/\s+/',
            ' ',
            trim($candidate['text'])
        );

        fwrite(
            STDERR,
            basename($path)
                .": format filter user_id belum dikenali.\n"
        );

        fwrite(
            STDERR,
            "Block: "
                .substr($oneLine, 0, 600)
                ."\n"
        );

        exit(14);
    }

    $replacement =
        $replacementAttribute
        .salesOwnerSelect(
            $isEdit,
            $marker
        );

    backupOnce($path);

    $source = substr_replace(
        $source,
        $replacement,
        $candidate['start'],
        $candidate['length']
    );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] {$marker}.\n";
}

function patchQuoteController(string $path): void
{
    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(
            STDERR,
            "QuoteController tidak dapat dibaca.\n"
        );

        exit(15);
    }

    if (
        str_contains(
            $source,
            'QUOTE SALES OWNER ROLE VALIDATION'
        )
    ) {
        echo "[SKIP] QuoteController validation sudah terpasang.\n";
        return;
    }

    backupOnce($path);

    /*
     * Store.
     */
    $storeMethodPos = strpos(
        $source,
        'function store'
    );

    $editMethodPos = strpos(
        $source,
        'function edit'
    );

    if (
        $storeMethodPos === false
        || $editMethodPos === false
    ) {
        fwrite(
            STDERR,
            "QuoteController store/edit method anchor tidak ditemukan.\n"
        );

        exit(16);
    }

    $storeChunk = substr(
        $source,
        $storeMethodPos,
        $editMethodPos - $storeMethodPos
    );

    $storeValidationRelative = strpos(
        $storeChunk,
        '$this->additionalValidation();'
    );

    if ($storeValidationRelative === false) {
        fwrite(
            STDERR,
            "Quote store additionalValidation anchor tidak ditemukan.\n"
        );

        exit(17);
    }

    $storeValidationPos =
        $storeMethodPos
        + $storeValidationRelative;

    $storeInsert =
        $storeValidationPos
        + strlen(
            '$this->additionalValidation();'
        );

    $source = substr_replace(
        $source,
        "\n\n"
        ."            /* QUOTE SALES OWNER ROLE VALIDATION - CREATE */\n"
        ."            \$this->validateSalesOwnerSelection();",
        $storeInsert,
        0
    );

    /*
     * Update after store insertion, recalculate positions.
     */
    $updateMethodPos = strpos(
        $source,
        'function update'
    );

    $searchAfterUpdate = strpos(
        $source,
        '$this->additionalValidation();',
        $updateMethodPos
    );

    if ($searchAfterUpdate === false) {
        fwrite(
            STDERR,
            "Quote update additionalValidation anchor tidak ditemukan.\n"
        );

        exit(18);
    }

    $updateInsert =
        $searchAfterUpdate
        + strlen(
            '$this->additionalValidation();'
        );

    $source = substr_replace(
        $source,
        "\n\n"
        ."        /* QUOTE SALES OWNER ROLE VALIDATION - EDIT */\n"
        ."        \$currentQuoteOwnerId = (int) \$this->quoteRepository\n"
        ."            ->findOrFail(\$id)\n"
        ."            ->user_id;\n\n"
        ."        \$this->validateSalesOwnerSelection(\n"
        ."            \$currentQuoteOwnerId\n"
        ."        );",
        $updateInsert,
        0
    );

    $additionalMethodPos = strpos(
        $source,
        'private function additionalValidation'
    );

    if ($additionalMethodPos === false) {
        fwrite(
            STDERR,
            "additionalValidation method tidak ditemukan.\n"
        );

        exit(19);
    }

    $docPos = strrpos(
        substr(
            $source,
            0,
            $additionalMethodPos
        ),
        '/**'
    );

    $helperInsert =
        $docPos !== false
            ? $docPos
            : $additionalMethodPos;

    $helper = <<<'PHP'
    /**
     * QUOTE SALES OWNER ROLE VALIDATION
     *
     * New owner must be Sales Admin or Sales User.
     * Existing legacy owner may stay unchanged on old Quotes.
     */
    private function validateSalesOwnerSelection(
        ?int $currentOwnerId = null
    ): void {
        $selectedOwnerId =
            (int) request(
                'user_id'
            );

        if (
            $currentOwnerId
            && $selectedOwnerId === $currentOwnerId
        ) {
            return;
        }

        if (
            ! app(
                \Webkul\Admin\Services\QuoteSalesOwnerService::class
            )->isEligible(
                $selectedOwnerId
            )
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' =>
                    'Sales Owner harus memiliki role Administrator, Sales Admin, atau Sales User.',
            ]);
        }
    }


PHP;

    $source = substr_replace(
        $source,
        $helper,
        $helperInsert,
        0
    );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] QuoteController Sales Owner server validation.\n";
}

$quoteViewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/quotes';

$createPath =
    $quoteViewRoot
    .'/create.blade.php';

$editPath =
    $quoteViewRoot
    .'/edit.blade.php';

$controllerPath =
    findQuoteController(
        $projectRoot
        .'/packages/Webkul/Admin/src/Http/Controllers'
    );

patchQuoteBlade(
    $createPath,
    false
);

patchQuoteBlade(
    $editPath,
    true
);

patchQuoteController(
    $controllerPath
);

echo "\n";
echo "Quote Sales Owner Role Filter V1.2 selesai.\n";
echo "Allowed: Administrator + Sales Admin + Sales User.\n";
echo "Quick Add Client tidak disentuh.\n";
