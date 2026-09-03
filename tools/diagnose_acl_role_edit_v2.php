<?php

declare(strict_types=1);

/**
 * ACL ROLE EDIT DIAGNOSTIC V2
 *
 * READ-ONLY.
 *
 * V1 only checked whether each raw Admin acl.php row had key + name.
 * V2 checks the EFFECTIVE ACL shape that Webkul\Core\Acl builds from dotted keys.
 * It also detects unsupported/extra fields that processSubAclItems() may mistake
 * for child ACL nodes.
 *
 * Run:
 * php tools/diagnose_acl_role_edit_v2.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$coreAclPath =
    $root . '/packages/Webkul/Core/src/Acl.php';

echo "ACL ROLE EDIT DIAGNOSTIC V2\n";
echo "===========================\n\n";

/*
|--------------------------------------------------------------------------
| 1. SHOW CORE ACL SOURCE AROUND THE FAILURE
|--------------------------------------------------------------------------
*/

if (is_file($coreAclPath)) {
    $lines =
        file(
            $coreAclPath,
            FILE_IGNORE_NEW_LINES
        );

    echo "Webkul\\Core\\Acl.php lines 82-120\n";
    echo "--------------------------------\n";

    for (
        $lineNo = 82;
        $lineNo <= 120;
        $lineNo++
    ) {
        $index =
            $lineNo - 1;

        if (isset($lines[$index])) {
            echo
                str_pad(
                    (string) $lineNo,
                    4,
                    ' ',
                    STR_PAD_LEFT
                )
                . ': '
                . $lines[$index]
                . PHP_EOL;
        }
    }

    echo PHP_EOL;
} else {
    echo "[WARN] Webkul\\Core\\Acl.php tidak ditemukan di path expected.\n\n";
}

/*
|--------------------------------------------------------------------------
| 2. GET EFFECTIVE ACL CONFIG USING THE SAME CORE CLASS
|--------------------------------------------------------------------------
*/

$items =
    null;

$source =
    null;

try {
    $aclObject =
        app(
            \Webkul\Core\Acl::class
        );

    $reflection =
        new ReflectionClass(
            $aclObject
        );

    if (
        $reflection->hasMethod(
            'getAclConfig'
        )
    ) {
        $method =
            $reflection->getMethod(
                'getAclConfig'
            );

        $method->setAccessible(
            true
        );

        $items =
            $method->invoke(
                $aclObject
            );

        $source =
            'Webkul\\Core\\Acl::getAclConfig()';
    }
} catch (Throwable $e) {
    echo
        "[WARN] Tidak dapat invoke getAclConfig(): "
        . $e->getMessage()
        . PHP_EOL;
}

if (!is_array($items)) {
    /*
     * Fallback: merge every package Config/acl.php we can find.
     */
    $items =
        [];

    $aclFiles =
        glob(
            $root
            . '/packages/*/*/src/Config/acl.php'
        )
        ?: [];

    $adminAcl =
        $root
        . '/packages/Webkul/Admin/src/Config/acl.php';

    if (
        is_file($adminAcl)
        && !in_array(
            $adminAcl,
            $aclFiles,
            true
        )
    ) {
        $aclFiles[] =
            $adminAcl;
    }

    foreach (
        $aclFiles
        as $file
    ) {
        try {
            $rows =
                require $file;

            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $items[] =
                        $row;
                }
            }
        } catch (Throwable $e) {
            echo
                "[WARN] Gagal load "
                . $file
                . ': '
                . $e->getMessage()
                . PHP_EOL;
        }
    }

    $source =
        'fallback package acl.php merge';
}

echo
    'Effective ACL source: '
    . $source
    . PHP_EOL;

echo
    'Effective ACL item count: '
    . count($items)
    . PHP_EOL
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| 3. RAW ITEM VALIDATION
|--------------------------------------------------------------------------
*/

$rawIssues =
    [];

$allowedMetadata = [
    'key',
    'name',
    'route',
    'sort',
];

foreach (
    $items
    as $index => $item
) {
    if (!is_array($item)) {
        $rawIssues[] = [
            'type' =>
                'not-array',

            'index' =>
                $index,

            'item' =>
                $item,
        ];

        continue;
    }

    $key =
        trim(
            (string) (
                $item['key']
                ?? ''
            )
        );

    $nameExists =
        array_key_exists(
            'name',
            $item
        );

    $name =
        trim(
            (string) (
                $item['name']
                ?? ''
            )
        );

    if ($key === '') {
        $rawIssues[] = [
            'type' =>
                'missing-key',

            'index' =>
                $index,

            'item' =>
                $item,
        ];
    }

    if (!$nameExists || $name === '') {
        $rawIssues[] = [
            'type' =>
                'missing-name',

            'index' =>
                $index,

            'key' =>
                $key,

            'item' =>
                $item,
        ];
    }

    $extra =
        array_values(
            array_diff(
                array_keys(
                    $item
                ),
                $allowedMetadata
            )
        );

    if ($extra) {
        $rawIssues[] = [
            'type' =>
                'extra-fields',

            'index' =>
                $index,

            'key' =>
                $key,

            'extra' =>
                $extra,

            'item' =>
                $item,
        ];
    }
}

echo "RAW ACL CHECK\n";
echo "-------------\n";

if (!$rawIssues) {
    echo
        "[OK] Tidak ada raw ACL issue.\n\n";
} else {
    echo
        '[FAIL] '
        . count($rawIssues)
        . " raw issue ditemukan.\n\n";

    foreach (
        $rawIssues
        as $issue
    ) {
        echo
            'type  : '
            . ($issue['type'] ?? '-')
            . PHP_EOL;

        echo
            'index : '
            . ($issue['index'] ?? '-')
            . PHP_EOL;

        echo
            'key   : '
            . (($issue['key'] ?? '') ?: '-')
            . PHP_EOL;

        if (!empty($issue['extra'])) {
            echo
                'extra : '
                . implode(
                    ', ',
                    $issue['extra']
                )
                . PHP_EOL;
        }

        echo
            'item  : '
            . var_export(
                $issue['item'] ?? null,
                true
            )
            . PHP_EOL;

        echo
            str_repeat(
                '-',
                70
            )
            . PHP_EOL;
    }

    echo PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| 4. REPRODUCE prepareAclItems()
|--------------------------------------------------------------------------
*/

$aclWithDotNotation =
    [];

foreach ($items as $item) {
    if (
        !is_array($item)
        || !isset($item['key'])
    ) {
        continue;
    }

    $aclWithDotNotation[
        $item['key']
    ] =
        $item;
}

$tree =
    Arr::undot(
        Arr::dot(
            $aclWithDotNotation
        )
    );

/*
 * Based on Webkul\Core\Acl's processSubAclItems behavior:
 * metadata is key/name/route/sort. Any remaining array entry is interpreted
 * as a child ACL node and therefore must itself contain name/route/sort.
 */
$treeIssues =
    [];

$walk =
    function (
        array $node,
        string $path
    ) use (
        &$walk,
        &$treeIssues
    ): void {
        if (
            !array_key_exists(
                'name',
                $node
            )
        ) {
            $treeIssues[] = [
                'type' =>
                    'processed-node-missing-name',

                'path' =>
                    $path,

                'keys' =>
                    array_keys(
                        $node
                    ),

                'node' =>
                    $node,
            ];
        }

        $children =
            $node;

        unset(
            $children['key'],
            $children['name'],
            $children['route'],
            $children['sort']
        );

        foreach (
            $children
            as $childKey => $child
        ) {
            $childPath =
                $path === ''
                    ? (string) $childKey
                    : $path
                        . '.'
                        . $childKey;

            if (!is_array($child)) {
                $treeIssues[] = [
                    'type' =>
                        'processed-child-not-array',

                    'path' =>
                        $childPath,

                    'value' =>
                        $child,

                    'parent' =>
                        $path,
                ];

                continue;
            }

            if (
                !array_key_exists(
                    'name',
                    $child
                )
            ) {
                $treeIssues[] = [
                    'type' =>
                        'processed-child-missing-name',

                    'path' =>
                        $childPath,

                    'keys' =>
                        array_keys(
                            $child
                        ),

                    'node' =>
                        $child,
                ];
            }

            $walk(
                $child,
                $childPath
            );
        }
    };

foreach (
    $tree
    as $topKey => $topNode
) {
    if (!is_array($topNode)) {
        $treeIssues[] = [
            'type' =>
                'top-node-not-array',

            'path' =>
                (string) $topKey,

            'value' =>
                $topNode,
        ];

        continue;
    }

    $walk(
        $topNode,
        (string) $topKey
    );
}

/*
 * De-duplicate diagnostic noise.
 */
$unique =
    [];

foreach (
    $treeIssues
    as $issue
) {
    $fingerprint =
        ($issue['type'] ?? '')
        . '|'
        . ($issue['path'] ?? '');

    $unique[$fingerprint] =
        $issue;
}

$treeIssues =
    array_values(
        $unique
    );

echo "PROCESSED ACL TREE CHECK\n";
echo "------------------------\n";

if (!$treeIssues) {
    echo
        "[OK] Tree hasil Arr::dot/undot tidak memiliki node rusak.\n";
} else {
    echo
        '[FAIL] '
        . count($treeIssues)
        . " processed-tree issue ditemukan.\n\n";

    foreach (
        $treeIssues
        as $number => $issue
    ) {
        echo
            '#'
            . ($number + 1)
            . PHP_EOL;

        echo
            'type : '
            . ($issue['type'] ?? '-')
            . PHP_EOL;

        echo
            'path : '
            . ($issue['path'] ?? '-')
            . PHP_EOL;

        if (isset($issue['keys'])) {
            echo
                'keys : '
                . implode(
                    ', ',
                    $issue['keys']
                )
                . PHP_EOL;
        }

        if (
            array_key_exists(
                'value',
                $issue
            )
        ) {
            echo
                'value: '
                . var_export(
                    $issue['value'],
                    true
                )
                . PHP_EOL;
        }

        /*
         * Avoid dumping an enormous subtree.
         */
        if (isset($issue['node'])) {
            $nodePreview =
                $issue['node'];

            if (
                count(
                    $nodePreview
                ) > 8
            ) {
                $nodePreview =
                    array_slice(
                        $nodePreview,
                        0,
                        8,
                        true
                    );

                $nodePreview['...'] =
                    '[truncated]';
            }

            echo
                'node : '
                . var_export(
                    $nodePreview,
                    true
                )
                . PHP_EOL;
        }

        echo
            str_repeat(
                '-',
                70
            )
            . PHP_EOL;
    }
}

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| 5. DOTTED PREFIX CHECK
|--------------------------------------------------------------------------
*/

$keys =
    [];

foreach (
    $items
    as $item
) {
    if (
        is_array($item)
        && isset($item['key'])
    ) {
        $keys[
            (string) $item['key']
        ] =
            true;
    }
}

$prefixIssues =
    [];

foreach (
    array_keys($keys)
    as $key
) {
    $parts =
        explode(
            '.',
            $key
        );

    if (count($parts) < 2) {
        continue;
    }

    array_pop(
        $parts
    );

    while ($parts) {
        $prefix =
            implode(
                '.',
                $parts
            );

        if (!isset($keys[$prefix])) {
            $prefixIssues[] = [
                'child' =>
                    $key,

                'missing_parent' =>
                    $prefix,
            ];
        }

        array_pop(
            $parts
        );
    }
}

echo "DOTTED KEY PARENT CHECK\n";
echo "-----------------------\n";

if (!$prefixIssues) {
    echo
        "[OK] Semua dotted ACL key memiliki parent ACL item.\n";
} else {
    echo
        '[FAIL] '
        . count($prefixIssues)
        . " missing parent ditemukan.\n\n";

    foreach (
        $prefixIssues
        as $issue
    ) {
        echo
            'child          : '
            . $issue['child']
            . PHP_EOL;

        echo
            'missing parent : '
            . $issue['missing_parent']
            . PHP_EOL;

        echo
            str_repeat(
                '-',
                70
            )
            . PHP_EOL;
    }
}

echo PHP_EOL;

if (
    !$rawIssues
    && !$treeIssues
    && !$prefixIssues
) {
    echo
        "HASIL: PASS\n";

    echo
        "Tidak ditemukan struktur ACL rusak oleh diagnostic V2.\n";

    echo
        "Kirim seluruh output ini; source Core Acl.php di atas akan menentukan langkah berikutnya.\n";

    exit(0);
}

echo
    "HASIL: FAIL\n";

echo
    "Kirim seluruh output ini. V2 READ-ONLY dan tidak mengubah source/database.\n";

exit(2);
