<x-admin::layouts>
    <x-slot:title>Financial Periods</x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-2xl font-bold">Financial Period Lock</h1>
            <p class="mt-1 text-sm text-gray-500">
                Close a month to prevent payment, expense, and PO financial changes for that period.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.financial-periods.store') }}" class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @csrf

            <div style="display:grid;grid-template-columns:220px 1fr auto;gap:12px;align-items:end;">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">Period *</label>
                    <input type="month" name="period" value="{{ old('period', now()->subMonth()->format('Y-m')) }}" class="w-full rounded-md border px-3 py-2" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">Closing Notes</label>
                    <input name="notes" value="{{ old('notes') }}" class="w-full rounded-md border px-3 py-2" placeholder="Finance closing approved">
                </div>

                <button class="primary-button">Close Period</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Period</th>
                        <th class="p-3">Range</th>
                        <th class="p-3">Closed By</th>
                        <th class="p-3">Closed At</th>
                        <th class="p-3">Notes</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locks as $lock)
                        <tr class="border-b">
                            <td class="p-3 font-bold">{{ $lock->period }}</td>
                            <td class="p-3">{{ $lock->starts_at?->format('Y-m-d') }} → {{ $lock->ends_at?->format('Y-m-d') }}</td>
                            <td class="p-3">{{ $lock->locked_by_name ?: '-' }}</td>
                            <td class="p-3">{{ $lock->locked_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="p-3">{{ $lock->notes ?: '-' }}</td>
                            <td class="p-3">
                                <form method="POST" action="{{ route('admin.financial-periods.destroy', $lock->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="secondary-button">Reopen</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center text-gray-500">No closed periods.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $locks->links() }}
    </div>
</x-admin::layouts>
