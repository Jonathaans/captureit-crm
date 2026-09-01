<x-admin::layouts>
    <x-slot:title>Audit Logs</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-2xl font-bold">Audit Trail</h1>
                <p class="mt-1 text-sm text-gray-500">Perubahan data bisnis yang tercatat otomatis.</p>
            </div>

            <a href="{{ route('admin.system-control.index') }}" class="secondary-button">
                Back
            </a>
        </div>

        <form method="GET" class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div style="display:grid;grid-template-columns:1fr 180px 180px auto;gap:10px;">
                <input name="q" value="{{ request('q') }}" placeholder="User / record / route" class="rounded-md border px-3 py-2">

                <input name="table" value="{{ request('table') }}" placeholder="Table" class="rounded-md border px-3 py-2">

                <select name="action" class="rounded-md border px-3 py-2">
                    <option value="">All Actions</option>
                    @foreach (['created', 'updated', 'deleted', 'restored'] as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>
                            {{ strtoupper($action) }}
                        </option>
                    @endforeach
                </select>

                <button class="primary-button">Apply</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Time</th>
                        <th class="p-3">User</th>
                        <th class="p-3">Action</th>
                        <th class="p-3">Table</th>
                        <th class="p-3">Record</th>
                        <th class="p-3">Changes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b align-top">
                            <td class="p-3 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="p-3">{{ $log->user_name ?: 'System' }}</td>
                            <td class="p-3 font-semibold">{{ strtoupper($log->action) }}</td>
                            <td class="p-3">{{ $log->table_name }}</td>
                            <td class="p-3">{{ $log->record_id }}</td>
                            <td class="p-3">
                                @if ($log->old_values)
                                    <details>
                                        <summary class="cursor-pointer font-semibold">Old</summary>
                                        <pre class="mt-2 max-w-[520px] overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif

                                @if ($log->new_values)
                                    <details class="mt-1">
                                        <summary class="cursor-pointer font-semibold">New</summary>
                                        <pre class="mt-2 max-w-[520px] overflow-auto whitespace-pre-wrap text-xs">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-gray-500">Belum ada audit log.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-admin::layouts>
