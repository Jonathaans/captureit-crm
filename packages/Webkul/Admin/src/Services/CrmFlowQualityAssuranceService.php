<?php

namespace Webkul\Admin\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CrmFlowQualityAssuranceService
{
    /* CRM_QA_AUTO_BACKUP_HOTFIX_V1 */
    /* CRM_FULL_QA_BACKUP_CENTER_V1 */

    public function run(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('crm-full-qa-flow-v1');
        }

        return Cache::remember(
            'crm-full-qa-flow-v1',
            now()->addMinutes(5),
            fn (): array => $this->perform()
        );
    }

    private function perform(): array
    {
        $startedAt = microtime(true);

        $flow = [
            $this->foundationStage(),
            $this->leadStage(),
            $this->quoteStage(),
            $this->invoiceStage(),
            $this->workOrderStage(),
            $this->deliveryStage(),
            $this->inventoryStage(),
            $this->procurementStage(),
            $this->communicationStage(),
            $this->governanceStage(),
        ];

        $checks = collect($flow)->flatMap(
            fn (array $stage): array => $stage['checks']
        );

        $counts = [
            'pass' => $checks->where('status', 'pass')->count(),
            'warning' => $checks->where('status', 'warning')->count(),
            'fail' => $checks->where('status', 'fail')->count(),
        ];

        $weighted = $counts['pass'] + ($counts['warning'] * 0.5);
        $score = $checks->count() > 0
            ? (int) round(($weighted / $checks->count()) * 100)
            : 0;

        return [
            'status' => $counts['fail'] > 0
                ? 'fail'
                : ($counts['warning'] > 0 ? 'warning' : 'pass'),
            'score' => $score,
            'counts' => $counts,
            'flow' => $flow,
            'checked_at' => now()->format('d M Y H:i:s'),
            'duration_ms' => (int) round(
                (microtime(true) - $startedAt) * 1000
            ),
        ];
    }

    private function foundationStage(): array
    {
        return $this->stage(
            '01',
            'System & Database',
            'Fondasi aplikasi, database, storage, queue, dan konfigurasi produksi.',
            'admin.system-control.index',
            [
                $this->guarded(
                    'Koneksi database',
                    function (): array {
                        DB::select('SELECT 1');

                        return $this->pass(
                            'Koneksi database',
                            DB::getDatabaseName().' dapat diakses.'
                        );
                    }
                ),
                $this->tablesCheck(
                    'Tabel CRM inti',
                    ['users', 'persons', 'leads', 'quotes', 'invoices']
                ),
                is_writable(storage_path())
                    ? $this->pass('Storage writable', storage_path())
                    : $this->fail(
                        'Storage writable',
                        'Folder storage tidak dapat ditulis.'
                    ),
                is_writable(base_path('bootstrap/cache'))
                    ? $this->pass(
                        'Bootstrap cache writable',
                        base_path('bootstrap/cache')
                    )
                    : $this->fail(
                        'Bootstrap cache writable',
                        'bootstrap/cache tidak dapat ditulis.'
                    ),
                $this->environmentCheck(),
                $this->failedJobsCheck(),
            ]
        );
    }

    private function leadStage(): array
    {
        return $this->stage(
            '02',
            'Lead',
            'Prospek masuk, kepemilikan sales, dan data customer awal.',
            'admin.leads.index',
            [
                $this->tablesCheck('Struktur lead', ['leads']),
                $this->routesCheck('Menu Lead', ['admin.leads.index']),
                $this->recordCountCheck('Jumlah lead', 'leads'),
                $this->orphanCheck(
                    'Lead tanpa sales owner valid',
                    'leads',
                    'user_id',
                    'users'
                ),
                $this->orphanCheck(
                    'Lead tanpa contact valid',
                    'leads',
                    'person_id',
                    'persons'
                ),
            ]
        );
    }

    private function quoteStage(): array
    {
        return $this->stage(
            '03',
            'Quotation',
            'Penawaran, item, project code, dan keterhubungan ke lead.',
            'admin.quotes.index',
            [
                $this->tablesCheck(
                    'Struktur quotation',
                    ['quotes', 'quote_items']
                ),
                $this->routesCheck('Menu Quotation', ['admin.quotes.index']),
                $this->recordCountCheck('Jumlah quotation', 'quotes'),
                $this->orphanCheck(
                    'Quotation tanpa lead valid',
                    'quotes',
                    'lead_id',
                    'leads'
                ),
                $this->orphanCheck(
                    'Item quotation tanpa header',
                    'quote_items',
                    'quote_id',
                    'quotes'
                ),
                $this->missingTextCheck(
                    'Quotation tanpa project code',
                    'quotes',
                    'project_code'
                ),
            ]
        );
    }

    private function invoiceStage(): array
    {
        return $this->stage(
            '04',
            'Invoice & Payment',
            'Invoice, item, pembayaran customer, dan nilai outstanding.',
            'admin.invoices.index',
            [
                $this->tablesCheck(
                    'Struktur invoice',
                    ['invoices', 'invoice_items', 'payments', 'expenses']
                ),
                $this->routesCheck('Menu Invoice', ['admin.invoices.index']),
                $this->recordCountCheck('Jumlah invoice', 'invoices'),
                $this->orphanCheck(
                    'Item invoice tanpa header',
                    'invoice_items',
                    'invoice_id',
                    'invoices'
                ),
                $this->orphanCheck(
                    'Payment tanpa invoice',
                    'payments',
                    'invoice_id',
                    'invoices'
                ),
                $this->negativeCheck(
                    'Invoice dengan balance negatif',
                    'invoices',
                    'balance_due'
                ),
                $this->missingTextCheck(
                    'Invoice tanpa project code',
                    'invoices',
                    'project_code'
                ),
            ]
        );
    }

    private function workOrderStage(): array
    {
        return $this->stage(
            '05',
            'Surat Perintah Kerja',
            'SPK hasil invoice, item pekerjaan, release, dan penyelesaian.',
            'admin.work-orders.index',
            [
                $this->tablesCheck(
                    'Struktur SPK',
                    ['work_orders', 'work_order_items']
                ),
                $this->routesCheck('Menu SPK', ['admin.work-orders.index']),
                $this->recordCountCheck('Jumlah SPK', 'work_orders'),
                $this->orphanCheck(
                    'SPK tanpa invoice valid',
                    'work_orders',
                    'invoice_id',
                    'invoices'
                ),
                $this->orphanCheck(
                    'Item SPK tanpa header',
                    'work_order_items',
                    'work_order_id',
                    'work_orders'
                ),
                $this->missingTextCheck(
                    'SPK tanpa nomor dokumen',
                    'work_orders',
                    'work_order_number'
                ),
            ]
        );
    }

    private function deliveryStage(): array
    {
        return $this->stage(
            '06',
            'Delivery Order',
            'Surat jalan, item pengiriman, relasi SPK/invoice, dan status return.',
            'admin.delivery-orders.index',
            [
                $this->tablesCheck(
                    'Struktur surat jalan',
                    ['delivery_orders', 'delivery_order_items']
                ),
                $this->routesCheck(
                    'Menu Delivery Order',
                    ['admin.delivery-orders.index']
                ),
                $this->recordCountCheck('Jumlah surat jalan', 'delivery_orders'),
                $this->orphanCheck(
                    'Surat jalan tanpa invoice valid',
                    'delivery_orders',
                    'invoice_id',
                    'invoices'
                ),
                $this->orphanCheck(
                    'Surat jalan tanpa SPK valid',
                    'delivery_orders',
                    'work_order_id',
                    'work_orders'
                ),
                $this->orphanCheck(
                    'Item surat jalan tanpa header',
                    'delivery_order_items',
                    'delivery_order_id',
                    'delivery_orders'
                ),
            ]
        );
    }

    private function inventoryStage(): array
    {
        return $this->stage(
            '07',
            'Inventory, Return & Damage',
            'Asset, alokasi, movement, return, missing, damaged, dan alasan kerusakan.',
            'admin.inventory.dashboard',
            [
                $this->tablesCheck(
                    'Struktur inventory',
                    [
                        'inventory_items',
                        'inventory_assets',
                        'inventory_stock_movements',
                        'delivery_order_inventory_allocations',
                    ]
                ),
                $this->routesCheck(
                    'Menu Inventory',
                    [
                        'admin.inventory.dashboard',
                        'admin.inventory.movements.index',
                    ]
                ),
                $this->recordCountCheck(
                    'Jumlah movement',
                    'inventory_stock_movements'
                ),
                $this->orphanCheck(
                    'Movement tanpa item valid',
                    'inventory_stock_movements',
                    'inventory_item_id',
                    'inventory_items'
                ),
                $this->orphanCheck(
                    'Movement tanpa asset valid',
                    'inventory_stock_movements',
                    'inventory_asset_id',
                    'inventory_assets'
                ),
                $this->orphanCheck(
                    'Alokasi tanpa surat jalan valid',
                    'delivery_order_inventory_allocations',
                    'delivery_order_id',
                    'delivery_orders'
                ),
                $this->damagedReasonCheck(),
            ]
        );
    }

    private function procurementStage(): array
    {
        return $this->stage(
            '08',
            'Vendor, PO & Expense',
            'Vendor master, purchase order, bukti bayar, dan posting expense.',
            'admin.purchase-orders.index',
            [
                $this->tablesCheck(
                    'Struktur procurement',
                    ['vendors', 'purchase_orders', 'purchase_order_items', 'expenses']
                ),
                $this->routesCheck(
                    'Menu Procurement',
                    ['admin.purchase-orders.index', 'admin.vendors.index']
                ),
                $this->recordCountCheck('Jumlah PO', 'purchase_orders'),
                $this->orphanCheck(
                    'PO tanpa invoice valid',
                    'purchase_orders',
                    'invoice_id',
                    'invoices'
                ),
                $this->orphanCheck(
                    'PO tanpa vendor master valid',
                    'purchase_orders',
                    'vendor_id',
                    'vendors'
                ),
                $this->orphanCheck(
                    'Expense tanpa invoice valid',
                    'expenses',
                    'invoice_id',
                    'invoices'
                ),
                $this->paidPoCheck(),
            ]
        );
    }

    private function communicationStage(): array
    {
        return $this->stage(
            '09',
            'Chat, Mail & Calendar',
            'Komunikasi internal, attachment, email user, dan event calendar.',
            'admin.internal-chat.index',
            [
                $this->tablesCheck(
                    'Struktur internal chat',
                    [
                        'internal_conversations',
                        'internal_conversation_members',
                        'internal_messages',
                        'internal_message_attachments',
                    ]
                ),
                $this->routesCheck(
                    'Menu Internal Chat',
                    ['admin.internal-chat.index']
                ),
                $this->orphanCheck(
                    'Pesan tanpa room valid',
                    'internal_messages',
                    'conversation_id',
                    'internal_conversations'
                ),
                $this->orphanCheck(
                    'Attachment tanpa pesan valid',
                    'internal_message_attachments',
                    'message_id',
                    'internal_messages'
                ),
                $this->attachmentFilesCheck(),
                $this->optionalTablesCheck(
                    'Struktur email user',
                    ['user_email_accounts', 'user_email_messages']
                ),
                $this->optionalTablesCheck(
                    'Struktur Google Calendar',
                    ['google_calendar_events']
                ),
            ]
        );
    }

    private function governanceStage(): array
    {
        return $this->stage(
            '10',
            'Audit & Governance',
            'Audit trail, incident, financial lock, dan kesiapan backup.',
            'admin.system-control.index',
            [
                $this->tablesCheck(
                    'Struktur audit',
                    ['crm_audit_logs', 'crm_system_incidents']
                ),
                $this->routesCheck(
                    'System Control',
                    ['admin.system-control.index']
                ),
                $this->openIncidentsCheck(),
                $this->optionalTablesCheck(
                    'Financial period lock',
                    ['financial_period_locks']
                ),
                class_exists(\ZipArchive::class)
                    ? $this->pass(
                        'PHP Zip extension',
                        'Siap membuat archive backup.'
                    )
                    : $this->fail(
                        'PHP Zip extension',
                        'Aktifkan extension zip untuk menjalankan backup.'
                    ),
            ]
        );
    }

    private function stage(
        string $number,
        string $title,
        string $description,
        string $routeName,
        array $checks
    ): array {
        $checks = array_values(array_filter($checks));
        $statuses = array_column($checks, 'status');

        $status = in_array('fail', $statuses, true)
            ? 'fail'
            : (in_array('warning', $statuses, true) ? 'warning' : 'pass');

        return [
            'number' => $number,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'url' => Route::has($routeName) ? route($routeName) : null,
            'checks' => $checks,
        ];
    }

    private function guarded(string $label, Closure $callback): array
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            return $this->fail(
                $label,
                mb_substr($exception->getMessage(), 0, 500)
            );
        }
    }

    private function tablesCheck(string $label, array $tables): array
    {
        return $this->guarded(
            $label,
            function () use ($label, $tables): array {
                $missing = array_values(array_filter(
                    $tables,
                    fn (string $table): bool => ! Schema::hasTable($table)
                ));

                return $missing === []
                    ? $this->pass($label, implode(', ', $tables).' tersedia.')
                    : $this->fail(
                        $label,
                        'Tabel belum tersedia: '.implode(', ', $missing).'.'
                    );
            }
        );
    }

    private function optionalTablesCheck(string $label, array $tables): array
    {
        return $this->guarded(
            $label,
            function () use ($label, $tables): array {
                $missing = array_values(array_filter(
                    $tables,
                    fn (string $table): bool => ! Schema::hasTable($table)
                ));

                return $missing === []
                    ? $this->pass($label, implode(', ', $tables).' tersedia.')
                    : $this->warning(
                        $label,
                        'Modul opsional belum lengkap: '.implode(', ', $missing).'.'
                    );
            }
        );
    }

    private function routesCheck(string $label, array $routes): array
    {
        $missing = array_values(array_filter(
            $routes,
            fn (string $route): bool => ! Route::has($route)
        ));

        return $missing === []
            ? $this->pass($label, 'Route dapat diakses.')
            : $this->fail(
                $label,
                'Route belum tersedia: '.implode(', ', $missing).'.'
            );
    }

    private function recordCountCheck(string $label, string $table): ?array
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return $this->guarded(
            $label,
            fn (): array => $this->pass(
                $label,
                number_format((int) DB::table($table)->count()).' record.'
            )
        );
    }

    private function orphanCheck(
        string $label,
        string $childTable,
        string $foreignKey,
        string $parentTable
    ): ?array {
        if (
            ! Schema::hasTable($childTable)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($childTable, $foreignKey)
            || ! Schema::hasColumn($parentTable, 'id')
        ) {
            return null;
        }

        return $this->guarded(
            $label,
            function () use (
                $label,
                $childTable,
                $foreignKey,
                $parentTable
            ): array {
                $count = DB::table($childTable.' as child')
                    ->leftJoin(
                        $parentTable.' as parent',
                        'child.'.$foreignKey,
                        '=',
                        'parent.id'
                    )
                    ->whereNotNull('child.'.$foreignKey)
                    ->whereNull('parent.id')
                    ->count();

                return $count > 0
                    ? $this->warning(
                        $label,
                        number_format($count).' record orphan ditemukan.'
                    )
                    : $this->pass($label, 'Tidak ada record orphan.');
            }
        );
    }

    private function missingTextCheck(
        string $label,
        string $table,
        string $column
    ): ?array {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
        ) {
            return null;
        }

        return $this->guarded(
            $label,
            function () use ($label, $table, $column): array {
                $count = DB::table($table)
                    ->where(function ($query) use ($column) {
                        $query
                            ->whereNull($column)
                            ->orWhereRaw('TRIM('.$column.') = ?', ['']);
                    })
                    ->count();

                return $count > 0
                    ? $this->warning(
                        $label,
                        number_format($count).' record perlu dilengkapi.'
                    )
                    : $this->pass($label, 'Semua record terisi.');
            }
        );
    }

    private function negativeCheck(
        string $label,
        string $table,
        string $column
    ): ?array {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
        ) {
            return null;
        }

        return $this->guarded(
            $label,
            function () use ($label, $table, $column): array {
                $count = DB::table($table)->where($column, '<', 0)->count();

                return $count > 0
                    ? $this->warning(
                        $label,
                        number_format($count).' nilai negatif ditemukan.'
                    )
                    : $this->pass($label, 'Tidak ada nilai negatif.');
            }
        );
    }

    private function damagedReasonCheck(): ?array
    {
        $table = 'inventory_stock_movements';

        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'notes')
        ) {
            return null;
        }

        return $this->guarded(
            'Damaged asset tanpa alasan',
            function () use ($table): array {
                $query = DB::table($table)
                    ->where(function ($builder) use ($table) {
                        if (Schema::hasColumn($table, 'to_status')) {
                            $builder->where('to_status', 'damaged');
                        }

                        if (Schema::hasColumn($table, 'movement_type')) {
                            $method = Schema::hasColumn($table, 'to_status')
                                ? 'orWhere'
                                : 'where';

                            $builder->{$method}('movement_type', 'damaged');
                        }
                    })
                    ->where(function ($builder) {
                        $builder
                            ->whereNull('notes')
                            ->orWhereRaw('TRIM(notes) = ?', ['']);
                    });

                $count = $query->count();

                return $count > 0
                    ? $this->warning(
                        'Damaged asset tanpa alasan',
                        number_format($count).' movement damaged belum memiliki note.'
                    )
                    : $this->pass(
                        'Damaged asset tanpa alasan',
                        'Semua movement damaged memiliki note.'
                    );
            }
        );
    }

    private function paidPoCheck(): ?array
    {
        $table = 'purchase_orders';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status')) {
            return null;
        }

        return $this->guarded(
            'PO Paid lengkap',
            function () use ($table): array {
                $hasProof = Schema::hasColumn($table, 'payment_proof_path');
                $hasExpense = Schema::hasColumn($table, 'expense_id');

                if (! $hasProof || ! $hasExpense) {
                    return $this->warning(
                        'PO Paid lengkap',
                        'Kolom payment_proof_path atau expense_id belum lengkap.'
                    );
                }

                $query = DB::table($table)
                    ->where('status', 'paid')
                    ->where(function ($builder) {
                        $builder
                            ->whereNull('payment_proof_path')
                            ->orWhereRaw(
                                'TRIM(payment_proof_path) = ?',
                                ['']
                            )
                            ->orWhereNull('expense_id');
                    });

                $count = $query->count();

                return $count > 0
                    ? $this->warning(
                        'PO Paid lengkap',
                        number_format($count)
                        .' PO paid belum memiliki bukti atau expense.'
                    )
                    : $this->pass(
                        'PO Paid lengkap',
                        'Semua PO paid memiliki bukti dan expense.'
                    );
            }
        );
    }

    private function attachmentFilesCheck(): ?array
    {
        $table = 'internal_message_attachments';

        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'storage_path')
        ) {
            return null;
        }

        return $this->guarded(
            'File attachment chat',
            function () use ($table): array {
                $paths = DB::table($table)
                    ->orderByDesc('id')
                    ->limit(250)
                    ->pluck('storage_path');

                $missing = $paths->filter(
                    fn ($path): bool => ! $this->storedFileExists((string) $path)
                )->count();

                return $missing > 0
                    ? $this->warning(
                        'File attachment chat',
                        number_format($missing)
                        .' dari '.number_format($paths->count())
                        .' file terbaru tidak ditemukan.'
                    )
                    : $this->pass(
                        'File attachment chat',
                        number_format($paths->count())
                        .' file terbaru tersedia.'
                    );
            }
        );
    }

    private function storedFileExists(string $path): bool
    {
        $path = trim($path);

        if ($path === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            $urlPath = (string) parse_url($path, PHP_URL_PATH);
            $position = strpos($urlPath, '/storage/');

            if ($position === false) {
                return true;
            }

            $path = substr($urlPath, $position + strlen('/storage/'));
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');

        foreach (['storage/app/', 'app/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $relative = substr($relative, strlen($prefix));
                break;
            }
        }

        $candidates = [
            storage_path('app/'.$relative),
            storage_path('app/public/'.$relative),
            public_path('storage/'.$relative),
            public_path($relative),
        ];

        return collect($candidates)->contains(
            fn (string $candidate): bool => is_file($candidate)
        );
    }

    private function environmentCheck(): array
    {
        if (! app()->environment('production')) {
            return $this->warning(
                'Production mode',
                'APP_ENV='.config('app.env').'; normal untuk komputer development.'
            );
        }

        if ((bool) config('app.debug')) {
            return $this->fail(
                'Production mode',
                'APP_DEBUG wajib false di production.'
            );
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            return $this->warning(
                'Production mode',
                'APP_URL production belum memakai HTTPS.'
            );
        }

        return $this->pass(
            'Production mode',
            'Production, debug off, dan HTTPS aktif.'
        );
    }

    private function failedJobsCheck(): ?array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return $this->warning(
                'Queue failures',
                'Tabel failed_jobs belum tersedia.'
            );
        }

        return $this->guarded(
            'Queue failures',
            function (): array {
                $count = (int) DB::table('failed_jobs')->count();

                return $count > 0
                    ? $this->warning(
                        'Queue failures',
                        number_format($count).' job gagal perlu diperiksa.'
                    )
                    : $this->pass('Queue failures', 'Tidak ada job gagal.');
            }
        );
    }

    private function openIncidentsCheck(): ?array
    {
        if (! Schema::hasTable('crm_system_incidents')) {
            return null;
        }

        return $this->guarded(
            'Open incidents',
            function (): array {
                $count = DB::table('crm_system_incidents')
                    ->whereNull('resolved_at')
                    ->count();

                return $count > 0
                    ? $this->warning(
                        'Open incidents',
                        number_format($count).' incident belum resolved.'
                    )
                    : $this->pass('Open incidents', 'Tidak ada incident terbuka.');
            }
        );
    }

    private function pass(string $label, string $detail): array
    {
        return compact('label', 'detail') + ['status' => 'pass'];
    }

    private function warning(string $label, string $detail): array
    {
        return compact('label', 'detail') + ['status' => 'warning'];
    }

    private function fail(string $label, string $detail): array
    {
        return compact('label', 'detail') + ['status' => 'fail'];
    }
}
