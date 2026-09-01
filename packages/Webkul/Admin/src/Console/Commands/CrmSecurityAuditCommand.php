<?php

namespace Webkul\Admin\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmSecurityAuditCommand extends Command
{
    protected $signature =
        'crm:security-audit {--export}';

    protected $description =
        'Audit CRM roles/users and highlight risky broad-access roles.';

    public function handle(): int
    {
        $this->info('CRM ROLE / PERMISSION AUDIT');
        $this->line(str_repeat('=', 27));

        if (! Schema::hasTable('roles')) {
            $this->error('Table roles tidak ditemukan.');
            return self::FAILURE;
        }

        $roleColumns =
            Schema::getColumnListing('roles');

        $select = array_values(
            array_intersect(
                [
                    'id',
                    'name',
                    'description',
                    'permission_type',
                ],
                $roleColumns
            )
        );

        $roles = DB::table('roles')
            ->select($select)
            ->orderBy('id')
            ->get();

        $userCounts = collect();

        if (
            Schema::hasTable('users')
            && Schema::hasColumn('users', 'role_id')
        ) {
            $userCounts = DB::table('users')
                ->selectRaw('role_id, COUNT(*) as total')
                ->groupBy('role_id')
                ->pluck('total', 'role_id');
        }

        $rows = [];

        foreach ($roles as $role) {
            $permissionType =
                property_exists($role, 'permission_type')
                    ? (string) $role->permission_type
                    : '-';

            $risk =
                strtolower((string) ($role->name ?? '')) !== 'administrator'
                && strtolower($permissionType) === 'all'
                    ? 'REVIEW: non-Administrator has ALL'
                    : 'OK';

            $row = [
                'id' => $role->id ?? '',
                'name' => $role->name ?? '',
                'permission_type' => $permissionType,
                'users' => (int) ($userCounts[$role->id] ?? 0),
                'risk' => $risk,
            ];

            $rows[] = $row;

            $this->line(
                sprintf(
                    '#%s %-24s permission=%-8s users=%-3d %s',
                    $row['id'],
                    $row['name'],
                    $row['permission_type'],
                    $row['users'],
                    $row['risk']
                )
            );
        }

        $this->newLine();
        $this->line('Recommended domain separation:');
        $this->line('Administrator : full system');
        $this->line('Sales Admin   : contacts/leads/quotes/events');
        $this->line('Sales User    : sales-owned operational records');
        $this->line('Admin Finance : invoices/payments/expenses/PO/report');
        $this->line('Head Warehouse: inventory/SJ/stock/maintenance');

        if ($this->option('export')) {
            $dir = storage_path('app/private/crm-reports');

            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $path =
                $dir
                .DIRECTORY_SEPARATOR
                .'security-audit-'
                .now()->format('Ymd-His')
                .'.csv';

            $handle = fopen($path, 'w');

            fputcsv(
                $handle,
                [
                    'Role ID',
                    'Role Name',
                    'Permission Type',
                    'Users',
                    'Risk',
                ]
            );

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);

            $this->info('Export: '.$path);
        }

        return self::SUCCESS;
    }
}
