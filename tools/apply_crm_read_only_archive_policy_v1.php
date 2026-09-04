<?php

declare(strict_types=1);

const ARCHIVE_PATCH_MARKER = 'CRM_READ_ONLY_ARCHIVE_POLICY_V1';

echo "CRM READ-ONLY ARCHIVE POLICY V1\n";
echo "===============================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "PATCH GAGAL: Simpan file ini di folder tools lalu jalankan dari root project Laravel.\n");
    exit(1);
}

$serviceRelative = 'packages/Webkul/Admin/src/Services/CrmReadOnlyArchivePolicyService.php';
$providerRelative = 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';
$providerPath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $providerRelative);
$servicePath = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $serviceRelative);

if (! is_file($providerPath)) {
    fwrite(STDERR, "PATCH GAGAL: Provider hardening tidak ditemukan. Pasang Full QA + Backup Center V1 dahulu.\n");
    exit(1);
}

$service = <<<'PHP'
<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmReadOnlyArchivePolicyService
{
    /* CRM_READ_ONLY_ARCHIVE_POLICY_V1 */

    public function assertMutable(Model $model, string $operation): void
    {
        $table = $model->getTable();

        if (
            $operation === 'create'
            && $table === 'inventory_stock_movements'
        ) {
            return;
        }

        if (
            $operation === 'create'
            && ! in_array(
                $table,
                [
                    'quote_items',
                    'invoice_items',
                    'payments',
                    'work_order_items',
                    'delivery_order_items',
                    'delivery_order_inventory_allocations',
                ],
                true
            )
        ) {
            return;
        }

        $reason = $this->archiveReason($model);

        if ($reason === null) {
            return;
        }

        $label = match ($operation) {
            'delete' => 'dihapus',
            'create' => 'ditambah detailnya',
            default => 'diubah',
        };

        throw ValidationException::withMessages([
            'archive' => sprintf(
                'Record arsip read-only tidak dapat %s. %s Gunakan dokumen revisi atau movement pembalik agar audit trail tetap utuh.',
                $label,
                $reason
            ),
        ]);
    }

    public function archiveReason(Model $model): ?string
    {
        $table = $model->getTable();

        if ($table === 'inventory_stock_movements') {
            return 'Inventory movement adalah ledger permanen (append-only).';
        }

        return match ($table) {
            'quotes' => $this->quoteReason($model),
            'quote_items' => $this->parentQuoteReason($model),
            'invoices' => $this->invoiceReason($model),
            'invoice_items', 'payments' => $this->parentInvoiceReason($model),
            'work_orders' => $this->statusReason(
                $this->raw($model, 'status'),
                ['completed', 'cancelled', 'canceled', 'archived'],
                'SPK'
            ),
            'work_order_items' => $this->parentStatusReason(
                $model,
                'work_order_id',
                'work_orders',
                ['completed', 'cancelled', 'canceled', 'archived'],
                'SPK'
            ),
            'delivery_orders' => $this->statusReason(
                $this->raw($model, 'status'),
                ['returned', 'completed', 'cancelled', 'canceled', 'archived'],
                'Surat Jalan'
            ),
            'delivery_order_items', 'delivery_order_inventory_allocations' =>
                $this->parentStatusReason(
                    $model,
                    'delivery_order_id',
                    'delivery_orders',
                    ['returned', 'completed', 'cancelled', 'canceled', 'archived'],
                    'Surat Jalan'
                ),
            default => null,
        };
    }

    private function quoteReason(Model $model): ?string
    {
        $id = (int) $this->raw($model, 'id');
        $expiredAt = $this->raw($model, 'expired_at');

        if ($id > 0 && DB::table('invoices')->where('quote_id', $id)->exists()) {
            return 'Quotation sudah dikonversi menjadi Invoice.';
        }

        if ($expiredAt !== null && strtotime((string) $expiredAt) <= time()) {
            return 'Quotation sudah kedaluwarsa.';
        }

        return null;
    }

    private function parentQuoteReason(Model $model): ?string
    {
        $quoteId = (int) $this->raw($model, 'quote_id');

        if ($quoteId <= 0) {
            return null;
        }

        $quote = DB::table('quotes')->where('id', $quoteId)->first();

        if ($quote === null) {
            return null;
        }

        if (DB::table('invoices')->where('quote_id', $quoteId)->exists()) {
            return 'Item Quotation terkunci karena Quotation sudah menjadi Invoice.';
        }

        if (
            isset($quote->expired_at)
            && $quote->expired_at !== null
            && strtotime((string) $quote->expired_at) <= time()
        ) {
            return 'Item Quotation terkunci karena Quotation sudah kedaluwarsa.';
        }

        return null;
    }

    private function invoiceReason(Model $model): ?string
    {
        $status = $this->normalized($this->raw($model, 'status'));
        $eventStatus = $this->normalized($this->raw($model, 'event_status'));

        if (in_array($status, ['paid', 'closed', 'void', 'cancelled', 'canceled', 'archived'], true)) {
            return 'Invoice berstatus final '.strtoupper($status).'.';
        }

        if (in_array($eventStatus, ['cancel', 'cancelled', 'canceled'], true)) {
            return 'Invoice terkait event yang sudah dibatalkan.';
        }

        return null;
    }

    private function parentInvoiceReason(Model $model): ?string
    {
        $invoiceId = (int) $this->raw($model, 'invoice_id');

        if ($invoiceId <= 0) {
            return null;
        }

        $invoice = DB::table('invoices')
            ->select(['status', 'event_status'])
            ->where('id', $invoiceId)
            ->first();

        if ($invoice === null) {
            return null;
        }

        $status = $this->normalized($invoice->status ?? null);
        $eventStatus = $this->normalized($invoice->event_status ?? null);

        if (in_array($status, ['paid', 'closed', 'void', 'cancelled', 'canceled', 'archived'], true)) {
            return 'Detail Invoice terkunci karena Invoice berstatus final '.strtoupper($status).'.';
        }

        if (in_array($eventStatus, ['cancel', 'cancelled', 'canceled'], true)) {
            return 'Detail Invoice terkunci karena event dibatalkan.';
        }

        return null;
    }

    private function parentStatusReason(
        Model $model,
        string $foreignKey,
        string $parentTable,
        array $finalStatuses,
        string $label
    ): ?string {
        $parentId = (int) $this->raw($model, $foreignKey);

        if ($parentId <= 0) {
            return null;
        }

        $status = DB::table($parentTable)
            ->where('id', $parentId)
            ->value('status');

        return $this->statusReason($status, $finalStatuses, $label);
    }

    private function statusReason(mixed $status, array $finalStatuses, string $label): ?string
    {
        $normalized = $this->normalized($status);

        return in_array($normalized, $finalStatuses, true)
            ? $label.' berstatus final '.strtoupper($normalized).'.'
            : null;
    }

    private function raw(Model $model, string $key): mixed
    {
        return $model->getRawOriginal($key) ?? $model->getAttribute($key);
    }

    private function normalized(mixed $value): string
    {
        return strtolower(trim((string) ($value ?? '')));
    }
}
PHP;

$provider = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($providerPath));

if (! str_contains($provider, 'use Webkul\\Admin\\Services\\CrmReadOnlyArchivePolicyService;')) {
    $importNeedle = 'use Webkul\\Admin\\Services\\CrmIncidentService;';

    if (substr_count($provider, $importNeedle) !== 1) {
        fwrite(STDERR, "PATCH GAGAL: Import anchor provider tidak cocok. Tidak ada file yang diubah.\n");
        exit(1);
    }

    $provider = str_replace(
        $importNeedle,
        $importNeedle."\nuse Webkul\\Admin\\Services\\CrmReadOnlyArchivePolicyService;",
        $provider
    );
}

if (! str_contains($provider, ARCHIVE_PATCH_MARKER)) {
    $bootNeedle = "    public function boot(): void\n    {\n";

    if (substr_count($provider, $bootNeedle) !== 1) {
        fwrite(STDERR, "PATCH GAGAL: Method boot provider tidak cocok. Tidak ada file yang diubah.\n");
        exit(1);
    }

    $guard = <<<'PHP'
        /* CRM_READ_ONLY_ARCHIVE_POLICY_V1
         * Final documents are readable/printable but cannot be modified or
         * deleted. Inventory movements are permanently append-only.
         */
        Event::listen(
            'eloquent.creating: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'create');
                }
            }
        );

        Event::listen(
            'eloquent.updating: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'update');
                }
            }
        );

        Event::listen(
            'eloquent.deleting: *',
            function (string $eventName, array $data) {
                $model = $data[0] ?? null;

                if ($model instanceof Model) {
                    app(CrmReadOnlyArchivePolicyService::class)
                        ->assertMutable($model, 'delete');
                }
            }
        );

PHP;

    $provider = str_replace($bootNeedle, $bootNeedle.$guard, $provider);
}

if (is_file($servicePath) && ! str_contains((string) file_get_contents($servicePath), ARCHIVE_PATCH_MARKER)) {
    fwrite(STDERR, "PATCH GAGAL: Service target sudah ada dan bukan milik patch ini. Tidak ada file yang diubah.\n");
    exit(1);
}

$originalProvider = (string) file_get_contents($providerPath);
$originalService = is_file($servicePath) ? (string) file_get_contents($servicePath) : null;
$suffix = '.before-crm-read-only-archive-policy-v1-'.date('Ymd-His').'.bak';

try {
    if (file_put_contents($providerPath.$suffix, $originalProvider, LOCK_EX) === false) {
        throw new RuntimeException('Gagal membuat backup provider.');
    }

    if (file_put_contents($servicePath, $service, LOCK_EX) === false) {
        throw new RuntimeException('Gagal menulis service archive policy.');
    }

    if (file_put_contents($providerPath, $provider, LOCK_EX) === false) {
        throw new RuntimeException('Gagal menulis provider.');
    }

    echo "[WRITE] {$serviceRelative}\n";
    echo "[WRITE] {$providerRelative}\n";

    if (function_exists('exec')) {
        foreach ([$servicePath, $providerPath] as $path) {
            $output = [];
            $code = 0;
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $code);

            if ($code !== 0) {
                throw new RuntimeException("PHP lint gagal:\n".implode("\n", $output));
            }
        }

        $output = [];
        $code = 0;
        $previous = getcwd();
        chdir($root);

        try {
            exec(escapeshellarg(PHP_BINARY).' artisan optimize:clear 2>&1', $output, $code);
        } finally {
            if ($previous !== false) {
                chdir($previous);
            }
        }

        if ($code !== 0) {
            throw new RuntimeException("optimize:clear gagal:\n".implode("\n", $output));
        }
    }
} catch (Throwable $exception) {
    @file_put_contents($providerPath, $originalProvider, LOCK_EX);

    if ($originalService === null) {
        @unlink($servicePath);
    } else {
        @file_put_contents($servicePath, $originalService, LOCK_EX);
    }

    fwrite(STDERR, "\nPATCH GAGAL: ".$exception->getMessage()."\nSemua file target dipulihkan.\n");
    exit(1);
}

echo "\nPATCH BERHASIL.\n";
echo "Quotation converted/expired, Invoice paid/cancel, SPK completed/cancel,\n";
echo "Surat Jalan returned/cancel, dan seluruh movement sekarang read-only.\n";
echo "Dokumen tetap dapat dibuka, dicetak, diekspor, dan dibackup.\n\n";
echo "Lanjutkan dengan:\n";
echo "php tools/check_crm_read_only_archive_policy_v1.php\n";
