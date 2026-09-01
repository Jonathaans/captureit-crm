<x-admin::layouts>
    <x-slot:title>
        System Control
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-2xl font-bold">System Control</h1>
            <p class="mt-1 text-sm text-gray-500">
                Production hardening: audit trail, incidents, backup, security audit, dan readiness.
            </p>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
                gap:14px;
            "
        >
            <a
                href="{{ route('admin.system-control.audit-logs') }}"
                class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="text-xs font-bold uppercase text-gray-500">Audit Logs</div>
                <div class="mt-2 text-3xl font-bold">{{ number_format($auditCount) }}</div>
                <div class="mt-2 text-sm text-gray-500">Siapa mengubah apa dan kapan.</div>
            </a>

            <a
                href="{{ route('admin.system-control.incidents') }}"
                class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="text-xs font-bold uppercase text-gray-500">Open Incidents</div>
                <div class="mt-2 text-3xl font-bold">{{ number_format($openIncidentCount) }}</div>
                <div class="mt-2 text-sm text-gray-500">Error production yang belum diselesaikan.</div>
            </a>

            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-bold uppercase text-gray-500">CLI Controls</div>
                <div class="mt-3 space-y-1 text-sm">
                    <code>php artisan crm:security-audit</code><br>
                    <code>php artisan crm:backup</code><br>
                    <code>php artisan crm:backup-verify</code><br>
                    <code>php artisan crm:production-readiness</code>
                </div>
            </div>
        </div>

        @if ($latestIncident)
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-bold uppercase text-gray-500">Latest Incident</div>
                <div class="mt-2 font-semibold">{{ $latestIncident->message }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ $latestIncident->last_seen_at?->format('Y-m-d H:i:s') }}
                    · {{ strtoupper($latestIncident->level) }}
                    · occurrence {{ $latestIncident->occurrence_count }}
                </div>
            </div>
        @endif
    </div>
</x-admin::layouts>
