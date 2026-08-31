<x-admin::layouts>
    <x-slot:title>
        {{ $maintenance->reference_number }} - Maintenance
    </x-slot>

    @php
        $asset = $maintenance->asset;
        $isActive = $maintenance->status === 'in_progress';
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route('admin.inventory.maintenance.index') }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Maintenance
            </a>

            <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
                {{ $maintenance->reference_number }}
            </p>

            <p class="mt-1 text-sm text-gray-500">
                {{ $asset?->asset_code ?: '-' }}
                &middot;
                {{ $asset?->item?->name ?: '-' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="rounded-full px-4 py-2 text-xs font-bold {{
                $maintenance->status === 'in_progress'
                    ? 'bg-yellow-100 text-yellow-700'
                    : (
                        $maintenance->status === 'completed'
                            ? 'bg-green-100 text-green-700'
                            : 'bg-gray-200 text-gray-700'
                    )
            }}">
                {{ strtoupper(str_replace('_', ' ', $maintenance->status)) }}
            </span>

            @if ($asset)
                <a
                    href="{{ route('admin.inventory.assets.edit', $asset->id) }}"
                    class="secondary-button"
                >
                    Open Asset
                </a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Asset Status</p>
            <p class="mt-1 font-bold">{{ strtoupper($asset?->status ?: '-') }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Asset Condition</p>
            <p class="mt-1 font-bold">{{ strtoupper($asset?->condition ?: '-') }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Started</p>
            <p class="mt-1 font-bold">{{ optional($maintenance->started_at)->format('d M Y H:i') ?: '-' }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Technician</p>
            <p class="mt-1 font-bold">{{ $maintenance->technician_name ?: '-' }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <p class="text-xs font-bold uppercase text-gray-500">
            Problem / Complaint
        </p>

        <p class="mt-2 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{{ $maintenance->problem }}</p>
    </div>

    @if ($isActive)
        <div class="mt-5 grid grid-cols-2 gap-4 max-lg:grid-cols-1">
            @if (bouncer()->hasPermission('inventory.maintenance.complete'))
                <form
                    method="POST"
                    action="{{ route(
                        'admin.inventory.maintenance.complete',
                        $maintenance->id
                    ) }}"
                    class="rounded-lg border border-green-200 bg-green-50 p-5 dark:border-green-900 dark:bg-green-900/20"
                >
                    @csrf
                    @method('PUT')

                    <p class="text-lg font-bold text-green-900 dark:text-green-200">
                        Mark Repaired
                    </p>

                    <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                        Selesaikan repair dan kembalikan asset menjadi AVAILABLE.
                    </p>

                    <div class="mt-4 grid gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Condition After Repair *
                            </label>

                            <select
                                name="result_condition"
                                required
                                class="w-full rounded-md border border-green-300 bg-white px-3 py-2.5 text-sm dark:border-green-800 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="good">GOOD</option>
                                <option value="fair">FAIR</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Technician / Vendor
                            </label>

                            <input
                                type="text"
                                name="technician_name"
                                value="{{ old(
                                    'technician_name',
                                    $maintenance->technician_name
                                ) }}"
                                maxlength="150"
                                class="w-full rounded-md border border-green-300 bg-white px-3 py-2.5 text-sm dark:border-green-800 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Repair Cost
                            </label>

                            <input
                                type="number"
                                name="repair_cost"
                                value="{{ old('repair_cost', 0) }}"
                                min="0"
                                step="0.01"
                                class="w-full rounded-md border border-green-300 bg-white px-3 py-2.5 text-sm dark:border-green-800 dark:bg-gray-950 dark:text-white"
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Repair Notes
                            </label>

                            <textarea
                                name="repair_notes"
                                rows="4"
                                maxlength="10000"
                                class="w-full rounded-md border border-green-300 bg-white px-3 py-2.5 text-sm dark:border-green-800 dark:bg-gray-950 dark:text-white"
                            >{{ old('repair_notes') }}</textarea>
                        </div>

                        <button
                            type="submit"
                            class="primary-button"
                            onclick="return confirm('Repair selesai dan asset siap kembali AVAILABLE?')"
                        >
                            Mark Repaired
                        </button>
                    </div>
                </form>
            @endif

            @if (bouncer()->hasPermission('inventory.maintenance.retire'))
                <form
                    method="POST"
                    action="{{ route(
                        'admin.inventory.maintenance.retire',
                        $maintenance->id
                    ) }}"
                    class="rounded-lg border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-900/20"
                >
                    @csrf
                    @method('PUT')

                    <p class="text-lg font-bold text-red-900 dark:text-red-200">
                        Retire Asset
                    </p>

                    <p class="mt-1 text-xs text-red-700 dark:text-red-300">
                        Gunakan jika asset tidak layak / tidak ekonomis diperbaiki.
                    </p>

                    <div class="mt-4 grid gap-4">
                        <textarea
                            name="retirement_reason"
                            rows="6"
                            maxlength="5000"
                            required
                            placeholder="Retirement Reason *"
                            class="w-full rounded-md border border-red-300 bg-white px-3 py-2.5 text-sm dark:border-red-800 dark:bg-gray-950 dark:text-white"
                        >{{ old('retirement_reason') }}</textarea>

                        <button
                            type="submit"
                            class="secondary-button"
                            onclick="return confirm('Asset akan menjadi RETIRED dan tidak tersedia lagi untuk Surat Jalan. Lanjutkan?')"
                        >
                            Retire Asset
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @else
        <div class="mt-5 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <p class="text-lg font-bold text-gray-800 dark:text-white">
                Maintenance Result
            </p>

            <div class="mt-4 grid grid-cols-4 gap-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                    <p class="text-xs uppercase text-gray-500">Result</p>
                    <p class="mt-1 font-bold uppercase">
                        {{
                            $maintenance->status === 'completed'
                                ? ($maintenance->result_condition ?: 'completed')
                                : 'retired'
                        }}
                    </p>
                </div>

                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                    <p class="text-xs uppercase text-gray-500">Repair Cost</p>
                    <p class="mt-1 font-bold">
                        Rp {{ number_format((float) $maintenance->repair_cost, 0, ',', '.') }}
                    </p>
                </div>

                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                    <p class="text-xs uppercase text-gray-500">Completed / Retired At</p>
                    <p class="mt-1 font-bold">
                        {{
                            optional(
                                $maintenance->completed_at
                                    ?: $maintenance->retired_at
                            )->format('d M Y H:i') ?: '-'
                        }}
                    </p>
                </div>

                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                    <p class="text-xs uppercase text-gray-500">Performed By</p>
                    <p class="mt-1 font-bold">
                        {{
                            $maintenance->completedBy?->name
                                ?: $maintenance->retiredBy?->name
                                ?: '-'
                        }}
                    </p>
                </div>
            </div>

            @if ($maintenance->repair_notes)
                <div class="mt-4">
                    <p class="text-xs font-bold uppercase text-gray-500">
                        Repair Notes
                    </p>

                    <p class="mt-2 whitespace-pre-wrap text-sm">{{ $maintenance->repair_notes }}</p>
                </div>
            @endif

            @if ($maintenance->retirement_reason)
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-4">
                    <p class="text-xs font-bold uppercase text-red-600">
                        Retirement Reason
                    </p>

                    <p class="mt-2 whitespace-pre-wrap text-sm text-red-800">{{ $maintenance->retirement_reason }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-200">
        Semua perubahan status dicatat ke Inventory Movements dengan reference
        <strong>{{ $maintenance->reference_number }}</strong>.
    </div>
</x-admin::layouts>
