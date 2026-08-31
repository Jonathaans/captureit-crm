<x-admin::layouts>
    <x-slot:title>
        Start Inventory Maintenance
    </x-slot>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <a
            href="{{ route('admin.inventory.maintenance.index') }}"
            class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
        >
            &larr; Back to Maintenance
        </a>

        <p class="mt-2 text-xl font-bold text-gray-800 dark:text-white">
            Start Maintenance
        </p>

        <p class="mt-1 text-sm text-gray-500">
            Hanya asset DAMAGED yang dapat masuk Maintenance.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.inventory.maintenance.store') }}"
        class="mt-4 grid gap-5 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
    >
        @csrf

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="mb-1.5 block text-sm font-semibold">
                Damaged Asset *
            </label>

            <select
                name="inventory_asset_id"
                required
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            >
                <option value="">Select Damaged Asset</option>

                @foreach ($damagedAssets as $damagedAsset)
                    <option
                        value="{{ $damagedAsset->id }}"
                        @selected(
                            (string) old(
                                'inventory_asset_id',
                                $asset?->id
                            ) === (string) $damagedAsset->id
                        )
                    >
                        {{ $damagedAsset->asset_code }}
                        - {{ $damagedAsset->item?->name ?: '-' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold">
                Problem / Complaint *
            </label>

            <textarea
                name="problem"
                rows="5"
                maxlength="5000"
                required
                placeholder="Contoh: lampu tidak menyala setelah return event."
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            >{{ old('problem') }}</textarea>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold">
                Technician / Vendor
            </label>

            <input
                type="text"
                name="technician_name"
                value="{{ old('technician_name') }}"
                maxlength="150"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            >
        </div>

        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            Setelah Start Maintenance:
            <strong>DAMAGED &rarr; MAINTENANCE</strong>.
            Asset tidak tersedia untuk Scan Allocation.
        </div>

        <div class="flex justify-end gap-2">
            <a
                href="{{ route('admin.inventory.maintenance.index') }}"
                class="secondary-button"
            >
                Cancel
            </a>

            <button type="submit" class="primary-button">
                Start Maintenance
            </button>
        </div>
    </form>
</x-admin::layouts>
