<?php

$root =
    realpath(
        __DIR__.'/..'
    );

if (! $root) {
    fwrite(
        STDERR,
        "Project root tidak ditemukan.\n"
    );

    exit(1);
}

function backupOnce(
    string $path,
    string $suffix
): void {
    $backup =
        $path
        .$suffix;

    if (! is_file($backup)) {
        if (! copy(
            $path,
            $backup
        )) {
            throw new RuntimeException(
                "Gagal membuat backup: {$backup}"
            );
        }
    }
}

function insertUse(
    string $source,
    string $anchor,
    string $use
): string {
    if (
        str_contains(
            $source,
            $use
        )
    ) {
        return $source;
    }

    if (
        ! str_contains(
            $source,
            $anchor
        )
    ) {
        fwrite(
            STDERR,
            "Provider use anchor tidak ditemukan: {$anchor}\n"
        );

        exit(10);
    }

    return str_replace(
        $anchor,
        $anchor
            ."\n"
            .$use,
        $source
    );
}

/*
|--------------------------------------------------------------------------
| 1. Patch InternalCommunicationServiceProvider
|--------------------------------------------------------------------------
*/

$provider =
    $root
    .'/packages/Webkul/Admin/src/Providers/InternalCommunicationServiceProvider.php';

if (! is_file($provider)) {
    fwrite(
        STDERR,
        "InternalCommunicationServiceProvider tidak ditemukan.\n"
    );

    exit(2);
}

$source =
    file_get_contents(
        $provider
    );

if ($source === false) {
    fwrite(
        STDERR,
        "Provider tidak dapat dibaca.\n"
    );

    exit(3);
}

backupOnce(
    $provider,
    '.before-internal-chat-audit-v1.bak'
);

$source =
    insertUse(
        $source,
        'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatController;',
        'use Webkul\Admin\Http\Controllers\InternalCommunication\InternalChatAuditController;'
    );

$source =
    insertUse(
        $source,
        'use Webkul\Admin\Services\WorkflowNotificationService;',
        'use Webkul\Admin\Models\InternalMessage;'
    );

$source =
    insertUse(
        $source,
        'use Webkul\Admin\Models\InternalMessage;',
        'use Webkul\Admin\Observers\InternalMessageAuditObserver;'
    );

$observerMarker =
    'InternalMessage::observe('
    ."\n"
    .'            InternalMessageAuditObserver::class'
    ."\n"
    .'        );';

if (
    ! str_contains(
        $source,
        'InternalMessageAuditObserver::class'
    )
    || ! str_contains(
        $source,
        'InternalMessage::observe('
    )
) {
    $bootAnchor =
        '        $this->registerRoutes();';

    if (
        ! str_contains(
            $source,
            $bootAnchor
        )
    ) {
        fwrite(
            STDERR,
            "Provider boot anchor tidak ditemukan.\n"
        );

        exit(11);
    }

    $source =
        str_replace(
            $bootAnchor,
            '        '
                .$observerMarker
                ."\n\n"
                .$bootAnchor,
            $source
        );
}

if (
    ! str_contains(
        $source,
        'admin.operational-dashboard.internal-chat-audit.index'
    )
) {
    $routeAnchor =
        "                    Route::get(\n"
        ."                        'internal-chat/attachments/{id}/download',";

    $anchorPos =
        strpos(
            $source,
            $routeAnchor
        );

    if ($anchorPos === false) {
        fwrite(
            STDERR,
            "Attachment route anchor tidak ditemukan.\n"
        );

        exit(12);
    }

    $namePos =
        strpos(
            $source,
            'admin.internal-chat.attachments.download',
            $anchorPos
        );

    $statementEnd =
        $namePos === false
            ? false
            : strpos(
                $source,
                ';',
                $namePos
            );

    if ($statementEnd === false) {
        fwrite(
            STDERR,
            "Akhir attachment route tidak ditemukan.\n"
        );

        exit(13);
    }

    $auditRoutes = <<<'PHP'

                    Route::get(
                        'operational-dashboard/internal-chat-audit',
                        [
                            InternalChatAuditController::class,
                            'index',
                        ]
                    )->name(
                        'admin.operational-dashboard.internal-chat-audit.index'
                    );

                    Route::get(
                        'operational-dashboard/internal-chat-audit/{messageId}',
                        [
                            InternalChatAuditController::class,
                            'show',
                        ]
                    )->name(
                        'admin.operational-dashboard.internal-chat-audit.show'
                    );
PHP;

    $source =
        substr_replace(
            $source,
            "\n"
            .$auditRoutes,
            $statementEnd
                + 1,
            0
        );
}

file_put_contents(
    $provider,
    $source
);

echo "[PASS] Internal message audit observer registered.\n";
echo "[PASS] Operational Dashboard audit routes registered.\n";

/*
|--------------------------------------------------------------------------
| 2. Add menu entry under Operational Dashboard when possible
|--------------------------------------------------------------------------
*/

$menu =
    $root
    .'/packages/Webkul/Admin/src/Config/menu.php';

$menuInstalled =
    false;

if (is_file($menu)) {
    $menuSource =
        file_get_contents(
            $menu
        );

    if (
        $menuSource !== false
        && ! str_contains(
            $menuSource,
            'admin.operational-dashboard.internal-chat-audit.index'
        )
    ) {
        backupOnce(
            $menu,
            '.before-internal-chat-audit-v1.bak'
        );

        $parentKey =
            null;

        $routePos =
            strpos(
                $menuSource,
                'admin.operational-dashboard.index'
            );

        if ($routePos !== false) {
            $prefix =
                substr(
                    $menuSource,
                    0,
                    $routePos
                );

            if (
                preg_match_all(
                    "/'key'\\s*=>\\s*'([^']+)'/",
                    $prefix,
                    $matches
                )
                && ! empty(
                    $matches[1]
                )
            ) {
                $parentKey =
                    end(
                        $matches[1]
                    );
            }
        }

        $menuKey =
            $parentKey
                ? $parentKey
                    .'.internal-chat-audit'
                : 'operational-dashboard.internal-chat-audit';

        $finalPos =
            strrpos(
                $menuSource,
                '];'
            );

        if ($finalPos !== false) {
            $entry =
                "\n    [\n"
                ."        'key'   => '"
                .$menuKey
                ."',\n"
                ."        'name'  => 'Internal Chat Audit',\n"
                ."        'route' => 'admin.operational-dashboard.internal-chat-audit.index',\n"
                ."        'sort'  => 999,\n"
                ."        'icon'  => 'icon-message',\n"
                ."    ],\n";

            $menuSource =
                substr_replace(
                    $menuSource,
                    $entry,
                    $finalPos,
                    0
                );

            file_put_contents(
                $menu,
                $menuSource
            );

            $menuInstalled =
                true;
        }
    } elseif (
        $menuSource !== false
        && str_contains(
            $menuSource,
            'admin.operational-dashboard.internal-chat-audit.index'
        )
    ) {
        $menuInstalled =
            true;
    }
}

echo $menuInstalled
    ? "[PASS] Operational Dashboard > Internal Chat Audit menu installed.\n"
    : "[WARN] Menu anchor unavailable. Audit route is installed and can still be opened directly.\n";

/*
|--------------------------------------------------------------------------
| 3. ACL entry
|--------------------------------------------------------------------------
*/

$acl =
    $root
    .'/packages/Webkul/Admin/src/Config/acl.php';

if (is_file($acl)) {
    $aclSource =
        file_get_contents(
            $acl
        );

    if (
        $aclSource !== false
        && ! str_contains(
            $aclSource,
            'admin.operational-dashboard.internal-chat-audit.index'
        )
    ) {
        backupOnce(
            $acl,
            '.before-internal-chat-audit-v1.bak'
        );

        $finalPos =
            strrpos(
                $aclSource,
                '];'
            );

        if ($finalPos !== false) {
            $entry = <<<'PHP'

    [
        'key'   => 'operational-dashboard.internal-chat-audit',
        'name'  => 'Internal Chat Audit',
        'route' => [
            'admin.operational-dashboard.internal-chat-audit.index',
            'admin.operational-dashboard.internal-chat-audit.show',
        ],
        'sort'  => 999,
    ],
PHP;

            $aclSource =
                substr_replace(
                    $aclSource,
                    "\n"
                    .$entry
                    ."\n",
                    $finalPos,
                    0
                );

            file_put_contents(
                $acl,
                $aclSource
            );

            echo "[PASS] Internal Chat Audit ACL entry installed.\n";
        }
    } else {
        echo "[PASS] Internal Chat Audit ACL already present.\n";
    }
}

echo "\n";
echo "Internal Chat Audit V1 patch selesai.\n";
echo "No chat message/controller replacement was performed.\n";
