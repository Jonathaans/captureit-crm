<x-admin::layouts>
    <x-slot:title>
        Create Inventory Item
    </x-slot>

    <form
        method="POST"
        action="{{ route('admin.inventory.items.store') }}"
        style="
            width:100%;
            max-width:1100px;
            margin:0 auto;
            display:flex;
            flex-direction:column;
            gap:14px;
        "
    >
        @csrf

        <div
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:16px;
                flex-wrap:wrap;
            "
        >
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Create Inventory Item
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    Buat master equipment serialized. Untuk quantity stock gunakan Consumables.
                </p>

                <div class="mt-3">
                    <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-bold text-blue-700">
                        TRACKING: SERIALIZED
                    </span>
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a
                    href="{{ route('admin.inventory.consumables.create') }}"
                    class="secondary-button"
                >
                    Create Consumable
                </a>

                <a
                    href="{{ route('admin.inventory.items.index') }}"
                    class="secondary-button"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save Item
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
                Inventory Item di halaman ini selalu <strong>Serialized</strong>.
                Unit fisiknya didaftarkan melalui menu Assets / Bulk Create + QR.
            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
                    gap:16px;
                "
            >
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Code *</label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="CAM-700D"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Name *</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="Canon EOS 700D"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Category</label>
                    <input
                        type="text"
                        name="category"
                        value="{{ old('category') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="Camera"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Unit *</label>
                    <input
                        type="text"
                        name="unit"
                        value="{{ old('unit', 'unit') }}"
                        class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                        placeholder="unit / set / box"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Tracking Type</label>
                    <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-bold text-blue-700">
                        Serialized
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium">Warehouse</label>
                    <input
                        type="text"
                        value="{{ $warehouse->name }}"
                        class="w-full rounded-md border bg-gray-50 px-3 py-2 text-gray-500 dark:border-gray-800 dark:bg-gray-950"
                        readonly
                    >
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1.5 block text-sm font-medium">Description</label>
                <textarea
                    name="description"
                    rows="4"
                    class="w-full rounded-md border px-3 py-2 dark:border-gray-800 dark:bg-gray-900"
                >{{ old('description') }}</textarea>
            </div>

            <label class="mt-4 flex items-center gap-2">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', 1))
                >
                <span class="text-sm font-medium">Active</span>
            </label>
        </div>
    </form>
</x-admin::layouts>
