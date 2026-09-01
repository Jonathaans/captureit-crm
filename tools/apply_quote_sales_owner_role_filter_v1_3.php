<?php

$projectRoot = realpath(__DIR__.'/..');

if (! $projectRoot) {
    fwrite(STDERR, "Project root tidak ditemukan.\n");
    exit(1);
}

function backupOnce(string $path): void
{
    $backup = $path.'.before-sales-owner-role-filter-v1-3.bak';

    if (! is_file($backup)) {
        copy($path, $backup);
    }
}

function quoteOwnerMarkup(bool $isEdit, string $marker): string
{
    $current = $isEdit ? '$quote->user_id' : 'null';
    $editFlag = $isEdit ? 'true' : 'false';

    $html = <<<'BLADE'
<!-- __MARKER__ -->
<?php
    $quoteSalesOwnerCurrentId = old(
        'user_id',
        __CURRENT__
    );

    $quoteSalesOwners = app(
        \Webkul\Admin\Services\QuoteSalesOwnerService::class
    )->options(
        __EDIT__
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
        <option value="">Select Sales Owner</option>

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

    <x-admin::form.control-group.error control-name="user_id" />
</x-admin::form.control-group>
BLADE;

    return str_replace(
        ['__MARKER__', '__CURRENT__', '__EDIT__'],
        [$marker, $current, $editFlag],
        $html
    );
}

function findControlGroup(string $source): ?array
{
    $open = '<x-admin::form.control-group';
    $close = '</x-admin::form.control-group>';
    $offset = 0;
    $matches = [];

    while (($start = strpos($source, $open, $offset)) !== false) {
        $next = substr($source, $start + strlen($open), 1);

        if ($next === '.') {
            $offset = $start + strlen($open);
            continue;
        }

        $end = strpos($source, $close, $start);

        if ($end === false) {
            break;
        }

        $end += strlen($close);
        $block = substr($source, $start, $end - $start);

        if (
            stripos($block, 'Sales Owner') !== false
            && str_contains($block, 'user_id')
        ) {
            $matches[] = [
                'start' => $start,
                'length' => $end - $start,
            ];
        }

        $offset = $end;
    }

    if (count($matches) === 1) {
        return $matches[0];
    }

    if (count($matches) > 1) {
        fwrite(STDERR, "Lebih dari satu Sales Owner control-group ditemukan.\n");
        exit(10);
    }

    return null;
}

function findPlainSelect(string $source): ?array
{
    $pattern =
        '/<select\b(?=[^>]*\bname\s*=\s*["\']user_id["\'])[^>]*>.*?<\/select>/si';

    if (
        preg_match_all(
            $pattern,
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        ) === 1
    ) {
        return [
            'start' => $matches[0][0][1],
            'length' => strlen($matches[0][0][0]),
        ];
    }

    return null;
}

function patchBlade(string $path, bool $isEdit): void
{
    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(STDERR, "Tidak dapat membaca {$path}\n");
        exit(11);
    }

    $marker = $isEdit
        ? 'QUOTE SALES OWNER ROLE FILTER EDIT V1.3'
        : 'QUOTE SALES OWNER ROLE FILTER CREATE V1.3';

    if (str_contains($source, $marker)) {
        echo "[SKIP] {$marker} sudah ada.\n";
        return;
    }

    $group = findControlGroup($source);

    if ($group) {
        backupOnce($path);

        $source = substr_replace(
            $source,
            quoteOwnerMarkup($isEdit, $marker),
            $group['start'],
            $group['length']
        );

        file_put_contents($path, $source);
        echo "[PASS] {$marker} via control-group.\n";
        return;
    }

    $plain = findPlainSelect($source);

    if ($plain) {
        backupOnce($path);

        $markup = quoteOwnerMarkup($isEdit, $marker);

        /*
         * Plain-select fallback: take only the PHP + select from our markup.
         * Keeping a full control-group would duplicate the existing label.
         */
        $phpStart = strpos($markup, '<?php');
        $selectEnd = strpos($markup, '</select>') + strlen('</select>');
        $replacement = substr(
            $markup,
            $phpStart,
            $selectEnd - $phpStart
        );

        $source = substr_replace(
            $source,
            "<!-- {$marker} -->\n".$replacement,
            $plain['start'],
            $plain['length']
        );

        file_put_contents($path, $source);
        echo "[PASS] {$marker} via plain user_id select.\n";
        return;
    }

    /*
     * Diagnostic fallback. It prints the real current markup around Sales Owner
     * and user_id, then exits without modifying the file.
     */
    fwrite(
        STDERR,
        basename($path)
            .": field Sales Owner belum dikenali.\n"
    );

    foreach (['Sales Owner', 'user_id'] as $needle) {
        $offset = 0;
        $count = 0;

        while (
            ($pos = stripos($source, $needle, $offset)) !== false
            && $count < 6
        ) {
            $context = substr(
                $source,
                max(0, $pos - 260),
                650
            );

            $context = preg_replace('/\s+/', ' ', $context);

            fwrite(
                STDERR,
                " {$needle} context "
                    .($count + 1)
                    .": "
                    .$context
                    ."\n"
            );

            $offset = $pos + strlen($needle);
            $count++;
        }
    }

    fwrite(
        STDERR,
        "Patch dihentikan tanpa mengubah file.\n"
    );

    exit(12);
}

function recursivePhpFiles(string $root): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (
            $file->isFile()
            && $file->getExtension() === 'php'
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
            && str_contains($source, 'function store')
            && str_contains($source, 'function update')
        ) {
            $matches[] = $path;
        }
    }

    if (count($matches) !== 1) {
        fwrite(
            STDERR,
            "QuoteController: expected 1, found "
                .count($matches)
                .".\n"
        );
        exit(13);
    }

    return $matches[0];
}

function patchController(string $path): void
{
    $source = file_get_contents($path);

    if ($source === false) {
        fwrite(STDERR, "QuoteController tidak dapat dibaca.\n");
        exit(14);
    }

    if (
        str_contains($source, 'validateSalesOwnerSelection')
        && str_contains($source, 'QUOTE SALES OWNER ROLE VALIDATION')
    ) {
        echo "[SKIP] Existing Sales Owner server validation retained.\n";
        return;
    }

    backupOnce($path);

    $needle = '$this->additionalValidation();';

    $storePos = strpos($source, 'function store');

    if ($storePos === false) {
        fwrite(STDERR, "store() tidak ditemukan.\n");
        exit(15);
    }

    $storeValidation = strpos($source, $needle, $storePos);

    if ($storeValidation === false) {
        fwrite(STDERR, "Store additionalValidation tidak ditemukan.\n");
        exit(16);
    }

    $insert = $storeValidation + strlen($needle);

    $source = substr_replace(
        $source,
        "\n\n"
        ."            /* QUOTE SALES OWNER ROLE VALIDATION V1.3 - CREATE */\n"
        ."            \$this->validateSalesOwnerSelection();",
        $insert,
        0
    );

    $updatePos = strpos($source, 'function update');

    if ($updatePos === false) {
        fwrite(STDERR, "update() tidak ditemukan.\n");
        exit(17);
    }

    $updateValidation = strpos($source, $needle, $updatePos);

    if ($updateValidation === false) {
        fwrite(STDERR, "Update additionalValidation tidak ditemukan.\n");
        exit(18);
    }

    $insert = $updateValidation + strlen($needle);

    $source = substr_replace(
        $source,
        "\n\n"
        ."        /* QUOTE SALES OWNER ROLE VALIDATION V1.3 - EDIT */\n"
        ."        \$currentQuoteOwnerId = (int) \$this->quoteRepository\n"
        ."            ->findOrFail(\$id)\n"
        ."            ->user_id;\n\n"
        ."        \$this->validateSalesOwnerSelection(\n"
        ."            \$currentQuoteOwnerId\n"
        ."        );",
        $insert,
        0
    );

    $methodPos = strpos(
        $source,
        'private function additionalValidation'
    );

    if ($methodPos === false) {
        fwrite(STDERR, "additionalValidation() method tidak ditemukan.\n");
        exit(19);
    }

    $docPos = strrpos(
        substr($source, 0, $methodPos),
        '/**'
    );

    $insert = $docPos !== false ? $docPos : $methodPos;

    $helper = <<<'PHP'
    /**
     * QUOTE SALES OWNER ROLE VALIDATION V1.3
     */
    private function validateSalesOwnerSelection(
        ?int $currentOwnerId = null
    ): void {
        $selectedOwnerId = (int) request('user_id');

        if (
            $currentOwnerId
            && $selectedOwnerId === $currentOwnerId
        ) {
            return;
        }

        if (
            ! app(
                \Webkul\Admin\Services\QuoteSalesOwnerService::class
            )->isEligible($selectedOwnerId)
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
        $insert,
        0
    );

    file_put_contents($path, $source);

    echo "[PASS] QuoteController Sales Owner server validation V1.3.\n";
}

$viewRoot =
    $projectRoot
    .'/packages/Webkul/Admin/src/Resources/views/quotes';

patchBlade(
    $viewRoot.'/create.blade.php',
    false
);

patchBlade(
    $viewRoot.'/edit.blade.php',
    true
);

$controller = findQuoteController(
    $projectRoot
        .'/packages/Webkul/Admin/src/Http/Controllers'
);

patchController($controller);

echo "\n";
echo "Quote Sales Owner Role Filter V1.3 selesai.\n";
echo "Allowed: Administrator, Sales Admin, Sales User.\n";
echo "Quick Add Client tidak disentuh.\n";
