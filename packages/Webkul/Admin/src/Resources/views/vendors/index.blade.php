<x-admin::layouts>
    <x-slot:title>Vendors</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-2xl font-bold">Vendor Master</h1>
                <p class="mt-1 text-sm text-gray-500">Vendor / outsource master generated from Purchase Orders.</p>
            </div>

            <a href="{{ route('admin.vendors.create') }}" class="primary-button">+ Create Vendor</a>
        </div>

        <form method="GET" class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex gap-2">
                <input name="q" value="{{ request('q') }}" class="flex-1 rounded-md border px-3 py-2" placeholder="Vendor / PIC / phone / email / NPWP">
                <button class="primary-button">Search</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">Vendor</th>
                        <th class="p-3">PIC</th>
                        <th class="p-3">Phone</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Payment Terms</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr class="border-b">
                            <td class="p-3 font-semibold">{{ $vendor->name }}</td>
                            <td class="p-3">{{ $vendor->pic_name ?: '-' }}</td>
                            <td class="p-3">{{ $vendor->phone ?: '-' }}</td>
                            <td class="p-3">{{ $vendor->email ?: '-' }}</td>
                            <td class="p-3">{{ $vendor->payment_terms ?: '-' }}</td>
                            <td class="p-3">{{ $vendor->is_active ? 'ACTIVE' : 'INACTIVE' }}</td>
                            <td class="p-3">
                                                                {{-- VENDOR NPWP INDEX ACTION V1 --}}
                                @if (! empty($vendor->npwp_image_path))
                                    <a
                                        href="{{ route('admin.vendors.npwp-image', $vendor->id) }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="secondary-button"
                                    >View NPWP</a>
                                @endif

                                <a href="{{ route('admin.vendors.edit', $vendor->id) }}" class="secondary-button">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center text-gray-500">Belum ada vendor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $vendors->links() }}
    </div>
</x-admin::layouts>
