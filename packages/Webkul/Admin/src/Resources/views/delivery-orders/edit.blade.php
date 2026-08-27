<x-admin::layouts>
    <x-slot:title>
        Edit {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    {{-- ========================================================= --}}
    {{-- HEADER --}}
    {{-- ========================================================= --}}

    <div
        class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="grid gap-2">
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                ← Back to Surat Jalan
            </a>

            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Edit {{ $deliveryOrder->delivery_order_number }}
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $deliveryOrder->project_code ?: '-' }}

                    @if ($deliveryOrder->project_name)
                        • {{ $deliveryOrder->project_name }}
                    @endif
                </p>
            </div>
        </div>
    </div>


    {{-- ========================================================= --}}
    {{-- FORM --}}
    {{-- ========================================================= --}}

    <form
        method="POST"
        action="{{ route(
            'admin.delivery-orders.update',
            $deliveryOrder->id
        ) }}"
        class="mt-4"
    >
        @csrf
        @method('PUT')


        {{-- ===================================================== --}}
        {{-- PROJECT INFO - READ ONLY --}}
        {{-- ===================================================== --}}

        <div
            class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Project Information
            </p>

            <div
                class="grid grid-cols-4 gap-5 max-xl:grid-cols-2 max-sm:grid-cols-1"
            >
                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Surat Jalan
                    </p>

                    <p class="mt-1 font-semibold text-gray-800 dark:text-white">
                        {{ $deliveryOrder->delivery_order_number }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Project Code
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->project_code ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Project Name
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->project_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Customer
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->customer_name ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Invoice
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->invoice_number ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Quote
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->quote_number ?: '-' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase text-gray-500">
                        Sales Person
                    </p>

                    <p class="mt-1 text-gray-800 dark:text-white">
                        {{ $deliveryOrder->sales_person_name ?: '-' }}
                    </p>
                </div>
            </div>
        </div>


        {{-- ===================================================== --}}
        {{-- EVENT INFORMATION --}}
        {{-- ===================================================== --}}

        <div
            class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Event Information
            </p>

            <div
                class="grid grid-cols-2 gap-5 max-sm:grid-cols-1"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Event Date
                    </label>

                    <input
                        type="date"
                        name="event_date"
                        value="{{ old(
                            'event_date',
                            $deliveryOrder->event_date?->format('Y-m-d')
                        ) }}"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('event_date')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Event Time
                    </label>

                    <input
                        type="time"
                        name="event_time"
                        value="{{ old(
                            'event_time',
                            $deliveryOrder->event_time
                                ? substr($deliveryOrder->event_time, 0, 5)
                                : ''
                        ) }}"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('event_time')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="col-span-2 max-sm:col-span-1">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Event Location
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="{{ old(
                            'location',
                            $deliveryOrder->location
                        ) }}"
                        placeholder="Contoh: Grand Ballroom Hotel Indonesia"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('location')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>


        {{-- ===================================================== --}}
        {{-- RECIPIENT / PIC --}}
        {{-- ===================================================== --}}

        <div
            class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Recipient / PIC
            </p>

            <div
                class="grid grid-cols-2 gap-5 max-sm:grid-cols-1"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Recipient Name
                    </label>

                    <input
                        type="text"
                        name="recipient_name"
                        value="{{ old(
                            'recipient_name',
                            $deliveryOrder->recipient_name
                        ) }}"
                        placeholder="Nama penerima"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('recipient_name')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Recipient Phone
                    </label>

                    <input
                        type="text"
                        name="recipient_phone"
                        value="{{ old(
                            'recipient_phone',
                            $deliveryOrder->recipient_phone
                        ) }}"
                        placeholder="08xxxxxxxxxx"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('recipient_phone')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        PIC Event
                    </label>

                    <input
                        type="text"
                        name="pic_name"
                        value="{{ old(
                            'pic_name',
                            $deliveryOrder->pic_name
                        ) }}"
                        placeholder="Nama PIC event"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('pic_name')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        PIC Phone
                    </label>

                    <input
                        type="text"
                        name="pic_phone"
                        value="{{ old(
                            'pic_phone',
                            $deliveryOrder->pic_phone
                        ) }}"
                        placeholder="08xxxxxxxxxx"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('pic_phone')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>


        {{-- ===================================================== --}}
        {{-- DELIVERY INFORMATION --}}
        {{-- ===================================================== --}}

        <div
            class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <p class="mb-5 text-base font-semibold text-gray-800 dark:text-white">
                Delivery Information
            </p>

            <div
                class="grid grid-cols-2 gap-5 max-sm:grid-cols-1"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Delivery Date
                    </label>

                    <input
                        type="date"
                        name="delivery_date"
                        value="{{ old(
                            'delivery_date',
                            $deliveryOrder->delivery_date?->format('Y-m-d')
                        ) }}"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('delivery_date')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Delivery Time
                    </label>

                    <input
                        type="time"
                        name="delivery_time"
                        value="{{ old(
                            'delivery_time',
                            $deliveryOrder->delivery_time
                                ? substr($deliveryOrder->delivery_time, 0, 5)
                                : ''
                        ) }}"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >

                    @error('delivery_time')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="col-span-2 max-sm:col-span-1">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
                    >
                        Delivery Address
                    </label>

                    <textarea
                        name="delivery_address"
                        rows="4"
                        placeholder="Alamat pengiriman / venue"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >{{ old(
                        'delivery_address',
                        $deliveryOrder->delivery_address
                    ) }}</textarea>

                    @error('delivery_address')
                        <p class="mt-1 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </div>


        {{-- ===================================================== --}}
        {{-- EQUIPMENT / ITEMS - MANUAL FIXED ROWS --}}
        {{-- ===================================================== --}}

        @php
            /*
             * Ambil data validation lama jika ada.
             * Kalau tidak ada, gunakan equipment yang sudah tersimpan.
             */
            $existingItems = old('items');

            if ($existingItems === null) {
                $existingItems = $deliveryOrder->items
                    ->map(function ($item) {
                        return [
                            'name'        => $item->name,
                            'description' => $item->description,
                            'quantity'    => $item->quantity,
                            'unit'        => $item->unit,
                            'notes'       => $item->notes,
                        ];
                    })
                    ->values()
                    ->toArray();
            }

            /*
             * Selalu tampilkan minimal 10 baris.
             * Kalau equipment tersimpan lebih dari 10,
             * semua tetap ditampilkan.
             */
            $equipmentRowCount = max(
                10,
                count($existingItems)
            );
        @endphp

        <div
            class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    Equipment / Items
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Isi barang yang dibawa untuk project.
                    Baris yang nama item-nya kosong tidak akan disimpan.
                    Data ini tidak mengubah database Product.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 dark:border-gray-800"
                        >
                            <th
                                class="w-[45px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                #
                            </th>

                            <th
                                class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                Item
                            </th>

                            <th
                                class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                Description
                            </th>

                            <th
                                class="w-[100px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                Qty
                            </th>

                            <th
                                class="w-[120px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                Unit
                            </th>

                            <th
                                class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                            >
                                Notes
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @for ($index = 0; $index < $equipmentRowCount; $index++)
                            @php
                                $item = $existingItems[$index] ?? [];
                            @endphp

                            <tr
                                class="border-b border-gray-100 dark:border-gray-800"
                            >
                                <td
                                    class="px-2 py-2 text-sm text-gray-500"
                                >
                                    {{ $index + 1 }}
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        name="items[{{ $index }}][name]"
                                        value="{{ $item['name'] ?? '' }}"
                                        placeholder="Camera"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                                    >
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        name="items[{{ $index }}][description]"
                                        value="{{ $item['description'] ?? '' }}"
                                        placeholder="Canon EOS R"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                                    >
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        name="items[{{ $index }}][quantity]"
                                        value="{{ $item['quantity'] ?? 1 }}"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                                    >
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        name="items[{{ $index }}][unit]"
                                        value="{{ $item['unit'] ?? 'unit' }}"
                                        placeholder="unit"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                                    >
                                </td>

                                <td class="px-2 py-2">
                                    <input
                                        type="text"
                                        name="items[{{ $index }}][notes]"
                                        value="{{ $item['notes'] ?? '' }}"
                                        placeholder="Catatan"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                                    >
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-950"
            >
                Tidak perlu mengisi semua baris. Equipment dengan nama kosong
                otomatis diabaikan saat Surat Jalan disimpan.
            </div>

            @error('items')
                <p class="mt-2 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror
        </div>


        {{-- ===================================================== --}}
        {{-- NOTES --}}
        {{-- ===================================================== --}}

        <div
            class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
        >
            <label
                class="mb-2 block text-base font-semibold text-gray-800 dark:text-white"
            >
                Notes
            </label>

            <textarea
                name="notes"
                rows="4"
                placeholder="Catatan tambahan Surat Jalan..."
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
            >{{ old(
                'notes',
                $deliveryOrder->notes
            ) }}</textarea>

            @error('notes')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror
        </div>


        {{-- ===================================================== --}}
        {{-- ACTION --}}
        {{-- ===================================================== --}}

        <div
            class="mt-4 flex justify-end gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="secondary-button"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="primary-button"
            >
                Save Surat Jalan
            </button>
        </div>
    </form>
</x-admin::layouts>