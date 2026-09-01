<x-admin::layouts>
    <x-slot:title>System Incidents</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-2xl font-bold">System Incidents</h1>
                <p class="mt-1 text-sm text-gray-500">Error critical/error dan failed queue yang terdeteksi.</p>
            </div>

            <a href="{{ route('admin.system-control.index') }}" class="secondary-button">Back</a>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Status</th>
                        <th class="p-3">Last Seen</th>
                        <th class="p-3">Level</th>
                        <th class="p-3">Message</th>
                        <th class="p-3">Count</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incidents as $incident)
                        <tr class="border-b align-top">
                            <td class="p-3 font-semibold">
                                {{ $incident->resolved_at ? 'RESOLVED' : 'OPEN' }}
                            </td>
                            <td class="p-3 whitespace-nowrap">{{ $incident->last_seen_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="p-3">{{ strtoupper($incident->level) }}</td>
                            <td class="p-3">
                                <div class="font-semibold">{{ $incident->message }}</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $incident->file }}{{ $incident->line ? ':'.$incident->line : '' }}
                                </div>
                            </td>
                            <td class="p-3">{{ $incident->occurrence_count }}</td>
                            <td class="p-3">
                                @if (! $incident->resolved_at)
                                    <form method="POST" action="{{ route('admin.system-control.incidents.resolve', $incident->id) }}">
                                        @csrf
                                        <button class="secondary-button">Resolve</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-gray-500">Tidak ada incident.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $incidents->links() }}
    </div>
</x-admin::layouts>
