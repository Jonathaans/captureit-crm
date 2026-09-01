<?php

namespace Webkul\Admin\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CrmAuditService
{
    private static bool $recording = false;

    public function record(
        string $action,
        Model $model
    ): void {
        if (self::$recording) {
            return;
        }

        $table = $model->getTable();

        if (
            ! in_array(
                $table,
                config('crm-hardening.audited_tables', []),
                true
            )
        ) {
            return;
        }

        if (
            ! Schema::hasTable('crm_audit_logs')
        ) {
            return;
        }

        self::$recording = true;

        try {
            $oldValues = null;
            $newValues = null;

            if ($action === 'created') {
                $newValues = $model->getAttributes();
            } elseif ($action === 'updated') {
                $changes = $model->getChanges();
                $old = [];

                foreach (array_keys($changes) as $key) {
                    $old[$key] = $model->getOriginal($key);
                }

                $oldValues = $old;
                $newValues = $changes;
            } elseif ($action === 'deleted') {
                $oldValues = $model->getAttributes();
            } elseif ($action === 'restored') {
                $newValues = $model->getAttributes();
            }

            $user = null;

            try {
                $user = auth()->guard('user')->user();
            } catch (Throwable) {
                $user = null;
            }

            $request = app()->runningInConsole()
                ? null
                : request();

            DB::table('crm_audit_logs')->insert([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => $action,
                'model_type' => $model::class,
                'table_name' => $table,
                'record_id' => $model->getKey() !== null
                    ? (string) $model->getKey()
                    : null,
                'route_name' => $request?->route()?->getName(),
                'url' => $request?->fullUrl(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request
                    ? substr((string) $request->userAgent(), 0, 500)
                    : null,
                'old_values' => $oldValues !== null
                    ? json_encode(
                        $this->mask($oldValues),
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                    : null,
                'new_values' => $newValues !== null
                    ? json_encode(
                        $this->mask($newValues),
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                    : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            /*
             * Audit logging must never break the business transaction.
             * Error monitoring is deliberately not called here to avoid
             * recursion when the database itself is unhealthy.
             */
        } finally {
            self::$recording = false;
        }
    }

    private function mask(array $values): array
    {
        $sensitive = array_map(
            'strtolower',
            config('crm-hardening.sensitive_keys', [])
        );

        foreach ($values as $key => $value) {
            $lower = strtolower((string) $key);

            if (
                collect($sensitive)->contains(
                    fn ($needle) =>
                        $needle !== ''
                        && str_contains($lower, $needle)
                )
            ) {
                $values[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->mask($value);
            }
        }

        return $values;
    }
}
