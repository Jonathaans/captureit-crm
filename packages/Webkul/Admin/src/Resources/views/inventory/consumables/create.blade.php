<x-admin::layouts>
    <x-slot:title>
        New Consumable
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.consumables.store') }}"
        style="width:100%;max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:14px;"
    >
        @csrf

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
             style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">
                    New Consumable
                </p>
                <p class="mt-1 text-sm text-gray-500">
                    Consumable selalu Quantity Tracking. Tidak memiliki Asset Code atau QR per unit.
                </p>
            </div>

            <div style="display:flex;gap:8px;">
                <a href="{{ route('admin.inventory.consumables.index') }}" class="secondary-button">
                    Cancel
                </a>
                <button type="submit" class="primary-button">
                    Save Consumable
                </button>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                Tracking Type: <strong>QUANTITY</strong>. Opening Stock otomatis membuat Inventory Movement.
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Code *</label>
                    <input type="text" name="code" value="{{ old('code') }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="PAPER-DNP" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="Ribbon DNP" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="Consumable">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Unit *</label>
                    <input type="text" name="unit" value="{{ old('unit', 'pcs') }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900" placeholder="box / pcs / roll" required>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Opening Stock</label>
                    <input type="number" step="0.01" min="0" name="opening_stock" value="{{ old('opening_stock', 0) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Minimum Stock</label>
                    <input type="number" step="0.01" min="0" name="minimum_stock" value="{{ old('minimum_stock', 0) }}" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium">Warehouse</label>
                <div class="rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                    {{ $warehouse->name }}
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium">Description</label>
                <textarea name="description" rows="4" class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900">{{ old('description') }}</textarea>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', 1))>
                Active Consumable
            </label>
        </div>
    </form>
</x-admin::layouts>
