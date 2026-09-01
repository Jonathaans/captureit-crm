<?php

/*
|--------------------------------------------------------------------------
| Quote Sales Owner Role Filter V1
|--------------------------------------------------------------------------
|
| Scope:
| - Quote Create
| - Quote Edit
|
| Sales Owner choices:
| - Sales Admin
| - Sales User
|
| Does NOT change:
| - Quick Add Client
| - Person / Organization
| - Invoice / PO / SJ / Inventory
| - user permissions / ACL
|
| The installer patches CURRENT customized Quote files in-place and makes
| backups. It does not replace the whole QuoteController or Quote Blade files.
|
*/

$projectRoot = realpath(
    __DIR__.'/..'
);

if (! $projectRoot) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

function backupOnce(
    string $path
): void {
    $backup =
        $path
        .'.before-sales-owner-role-filter.bak';

    if (! is_file($backup)) {
        copy(
            $path,
            $backup
        );
    }
}

function recursiveFiles(
    string $root,
    string $suffix
): array {
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            )
        );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && str_ends_with(
                strtolower(
                    $file->getFilename()
                ),
                strtolower(
                    $suffix
                )
            )
        ) {
            $files[] =
                $file->getPathname();
        }
    }

    return $files;
}

function exactlyOne(
    array $matches,
    string $label
): string {
    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "{$label}: expected 1 file, found "
                .count($matches)
                .".\n"
        );

        foreach ($matches as $match) {
            fwrite(
                STDERR,
                " - {$match}\n"
            );
        }

        exit(10);
    }

    return $matches[0];
}

function findQuoteController(
    string $root
): string {
    $matches = [];

    foreach (
        recursiveFiles(
            $root,
            '.php'
        )
        as $path
    ) {
        $source =
            file_get_contents(
                $path
            );

        if (
            $source !== false
            && str_contains(
                $source,
                'class QuoteController'
            )
            && str_contains(
                $source,
                'function create'
            )
            && str_contains(
                $source,
                'function update'
            )
        ) {
            $matches[] =
                $path;
        }
    }

    return exactlyOne(
        $matches,
        'QuoteController'
    );
}

/**
 * Replace only the Quote user_id attribute renderer.
 *
 * Existing code renders:
 * expired_at + user_id
 * through <x-admin::attributes>.
 *
 * We keep expired_at in that component and replace only user_id with a
 * dedicated Sales Owner select.
 */
function patchSalesOwnerBlade(
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

    $source =
        file_get_contents(
            $path
        );

    if ($source === false) {
        fwrite(
            STDERR,
            "Quote Blade tidak dapat dibaca: {$path}\n"
        );

        exit(12);
    }

    $marker =
        $isEdit
            ? 'QUOTE SALES OWNER ROLE FILTER EDIT'
            : 'QUOTE SALES OWNER ROLE FILTER CREATE';

    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        echo "[SKIP] {$marker} sudah ada.\n";
        return;
    }

    /*
     * We require the exact semantic anchor: one x-admin::attributes block
     * whose custom-attributes expression contains BOTH expired_at and user_id.
     */
    $pattern =
        '/<x-admin::attributes\b'
        .'(?:(?!<x-admin::attributes\b).)*?'
        .'expired_at'
        .'(?:(?!<x-admin::attributes\b).)*?'
        .'user_id'
        .'(?:(?!\/>).)*?\/>/s';

    if (
        preg_match_all(
            $pattern,
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        ) !== 1
    ) {
        fwrite(
            STDERR,
            basename($path)
                .": block Quote attributes expired_at + user_id tidak ditemukan secara unik.\n"
        );

        exit(13);
    }

    $oldBlock =
        $matches[0][0][0];

    /*
     * Preserve the project's existing custom-validations and entity expression.
     * We only remove user_id from the code list.
     */
    $expiredOnly =
        preg_replace(
            "/\\['expired_at'\\s*,\\s*'user_id'\\]/",
            "['expired_at']",
            $oldBlock,
            1,
            $replaceCount
        );

    if ($replaceCount !== 1) {
        /*
         * Support the inverse array order just in case.
         */
        $expiredOnly =
            preg_replace(
                "/\\['user_id'\\s*,\\s*'expired_at'\\]/",
                "['expired_at']",
                $oldBlock,
                1,
                $replaceCount
            );
    }

    if ($replaceCount !== 1) {
        fwrite(
            STDERR,
            basename($path)
                .": array code expired_at/user_id tidak dapat dipisahkan.\n"
        );

        exit(14);
    }

    $currentExpression =
        $isEdit
            ? '$quote->user_id'
            : 'null';

    $select = <<<'BLADE'

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

    $select =
        str_replace(
            [
                '__MARKER__',
                '__CURRENT__',
                '__IS_EDIT__',
            ],
            [
                $marker,
                $currentExpression,
                $isEdit
                    ? 'true'
                    : 'false',
            ],
            $select
        );

    $newBlock =
        $expiredOnly
        .$select;

    backupOnce(
        $path
    );

    $source =
        substr_replace(
            $source,
            $newBlock,
            $matches[0][0][1],
            strlen(
                $oldBlock
            )
        );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] {$marker}.\n";
}

function patchQuoteController(
    string $path
): void {
    $source =
        file_get_contents(
            $path
        );

    if ($source === false) {
        fwrite(
            STDERR,
            "QuoteController tidak dapat dibaca.\n"
        );

        exit(15);
    }

    $marker =
        'QUOTE SALES OWNER ROLE VALIDATION';

    if (
        str_contains(
            $source,
            $marker
        )
    ) {
        echo "[SKIP] QuoteController validation sudah ada.\n";
        return;
    }

    backupOnce(
        $path
    );

    /*
     * CREATE/STORE
     *
     * Validate only the normal full Quote form.
     * Existing quick-add behavior remains untouched.
     */
    $storeNeedle =
        '$this->additionalValidation();';

    $storePos =
        strpos(
            $source,
            $storeNeedle
        );

    if ($storePos === false) {
        fwrite(
            STDERR,
            "QuoteController store additionalValidation anchor tidak ditemukan.\n"
        );

        exit(16);
    }

    $storeInsert =
        $storePos
        + strlen(
            $storeNeedle
        );

    $source =
        substr_replace(
            $source,
            "\n\n"
            ."            /* {$marker} - CREATE */\n"
            ."            \$this->validateSalesOwnerSelection();",
            $storeInsert,
            0
        );

    /*
     * UPDATE
     *
     * Find the NEXT additionalValidation() after update() method begins.
     */
    $updateMethodPos =
        strpos(
            $source,
            'function update'
        );

    if ($updateMethodPos === false) {
        fwrite(
            STDERR,
            "QuoteController update() tidak ditemukan.\n"
        );

        exit(17);
    }

    $updateValidationPos =
        strpos(
            $source,
            $storeNeedle,
            $updateMethodPos
        );

    if ($updateValidationPos === false) {
        fwrite(
            STDERR,
            "QuoteController update additionalValidation anchor tidak ditemukan.\n"
        );

        exit(18);
    }

    $updateInsert =
        $updateValidationPos
        + strlen(
            $storeNeedle
        );

    $source =
        substr_replace(
            $source,
            "\n\n"
            ."        /* {$marker} - EDIT */\n"
            ."        \$currentQuoteOwnerId = (int) \$this->quoteRepository\n"
            ."            ->findOrFail(\$id)\n"
            ."            ->user_id;\n\n"
            ."        \$this->validateSalesOwnerSelection(\n"
            ."            \$currentQuoteOwnerId\n"
            ."        );",
            $updateInsert,
            0
        );

    /*
     * Add helper immediately before additionalValidation().
     */
    $helperAnchor =
        'private function additionalValidation';

    $helperPos =
        strpos(
            $source,
            $helperAnchor
        );

    if ($helperPos === false) {
        fwrite(
            STDERR,
            "QuoteController additionalValidation method tidak ditemukan.\n"
        );

        exit(19);
    }

    /*
     * Include the docblock immediately preceding additionalValidation,
     * so helper is inserted cleanly before it.
     */
    $docPos =
        strrpos(
            substr(
                $source,
                0,
                $helperPos
            ),
            '/**'
        );

    $insertPos =
        $docPos !== false
            ? $docPos
            : $helperPos;

    $helper = <<<'PHP'
    /**
     * QUOTE SALES OWNER ROLE VALIDATION
     *
     * A new Sales Owner must have role Sales Admin or Sales User.
     * A legacy owner on an existing Quote may remain unchanged.
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
                    'Sales Owner harus memiliki role Sales Admin atau Sales User.',
            ]);
        }
    }


PHP;

    $source =
        substr_replace(
            $source,
            $helper,
            $insertPos,
            0
        );

    file_put_contents(
        $path,
        $source
    );

    echo "[PASS] QuoteController Sales Owner server validation.\n";
}

/*
|--------------------------------------------------------------------------
| Locate current customized files
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Patch
|--------------------------------------------------------------------------
*/

patchSalesOwnerBlade(
    $createPath,
    false
);

patchSalesOwnerBlade(
    $editPath,
    true
);

patchQuoteController(
    $controllerPath
);

echo "\n";
echo "Quote Sales Owner Role Filter selesai.\n";
echo "Allowed roles: Sales Admin, Sales User.\n";
echo "Quick Add Client tidak dipatch.\n";
