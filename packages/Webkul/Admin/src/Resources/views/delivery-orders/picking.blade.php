<x-admin::layouts>
    <x-slot:title>
        Picking / OUT - {{ $deliveryOrder->delivery_order_number }}
    </x-slot>

    @php
        $status = strtolower(
            $deliveryOrder->status ?: 'draft'
        );

        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format((float) $value, 2, '.', ''),
                    '0'
                ),
                '.'
            );
        };

        $statusClass = static function ($allocationStatus) {
            return match ($allocationStatus) {
                'allocated' =>
                    'bg-blue-100 text-blue-700',

                'picked' =>
                    'bg-yellow-100 text-yellow-700',

                'out' =>
                    'bg-purple-100 text-purple-700',

                'return_pending' =>
                    'bg-orange-100 text-orange-700',

                default =>
                    'bg-gray-100 text-gray-700',
            };
        };
    @endphp

    <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white p-4 max-sm:flex-wrap dark:border-gray-800 dark:bg-gray-900">
        <div>
            <a
                href="{{ route(
                    'admin.delivery-orders.show',
                    $deliveryOrder->id
                ) }}"
                class="text-sm text-gray-600 hover:text-brandColor dark:text-gray-300"
            >
                &larr; Back to Surat Jalan
            </a>

            <div class="mt-2 flex items-center gap-3">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    Picking / OUT
                </p>

                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-bold uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $status }}
                </span>
            </div>

            <p class="mt-1 text-sm text-gray-500">
                {{ $deliveryOrder->delivery_order_number }}
                · {{ $deliveryOrder->project_code ?: '-' }}

                @if ($deliveryOrder->project_name)
                    · {{ $deliveryOrder->project_name }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if (
                $canOperate
                && $summary['allocated'] > 0
                && bouncer()->hasPermission('delivery-orders.picking')
            )
                <form
                    method="POST"
                    action="{{ route(
                        'admin.delivery-orders.picking.mark-all',
                        $deliveryOrder->id
                    ) }}"
                >
                    @csrf
                    @method('PUT')

                    <button
                        type="submit"
                        class="secondary-button"
                        onclick="return confirm('Tandai seluruh inventory ALLOCATED sebagai PICKED? Pastikan barang sudah benar-benar diambil dari storage.')"
                    >
                        Mark All Picked
                    </button>
                </form>
            @endif

            @if (
                $canOperate
                && $allPicked
                && bouncer()->hasPermission('delivery-orders.out')
            )
                <form
                    method="POST"
                    action="{{ route(
                        'admin.delivery-orders.picking.out',
                        $deliveryOrder->id
                    ) }}"
                >
                    @csrf
                    @method('PUT')

                    <button
                        type="submit"
                        class="primary-button"
                        onclick="return confirm('Confirm seluruh barang OUT dari warehouse? Untuk quantity, physical stock akan dikurangi sekarang.')"
                    >
                        Confirm OUT
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-4 grid grid-cols-4 gap-4 max-lg:grid-cols-2 max-sm:grid-cols-1">
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs font-semibold uppercase text-blue-600">
                Allocated
            </p>

            <p class="mt-2 text-2xl font-bold text-blue-800">
                {{ $summary['allocated'] }}
            </p>
        </div>

        <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
            <p class="text-xs font-semibold uppercase text-yellow-600">
                Picked
            </p>

            <p class="mt-2 text-2xl font-bold text-yellow-800">
                {{ $summary['picked'] }}
            </p>
        </div>

        <div class="rounded-lg border border-purple-200 bg-purple-50 p-4">
            <p class="text-xs font-semibold uppercase text-purple-600">
                Out
            </p>

            <p class="mt-2 text-2xl font-bold text-purple-800">
                {{ $summary['out'] }}
            </p>
        </div>

        <div class="rounded-lg border border-orange-200 bg-orange-50 p-4">
            <p class="text-xs font-semibold uppercase text-orange-600">
                Return Pending
            </p>

            <p class="mt-2 text-2xl font-bold text-orange-800">
                {{ $summary['return_pending'] }}
            </p>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-5">
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                Picking List
            </p>

            <p class="mt-1 text-xs text-gray-500">
                PICKED berarti barang sudah diambil dari storage tetapi belum keluar warehouse.
                OUT berarti barang benar-benar meninggalkan warehouse.
            </p>
        </div>

        @if ($allocations->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
                Tidak ada inventory allocation aktif.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800">
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                #
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                Equipment
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                Inventory Item
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                Actual Item
                            </th>

                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">
                                Qty
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                Status
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                Picked At
                            </th>

                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500">
                                OUT At
                            </th>

                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($allocations as $allocation)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-4 text-sm text-gray-500">
                                    {{ $loop->iteration }}
                                </td>

                                <td class="px-3 py-4">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                        {{ $allocation->deliveryOrderItem?->name ?: '-' }}
                                    </p>

                                    @if ($allocation->deliveryOrderItem?->description)
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ $allocation->deliveryOrderItem->description }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-3 py-4">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                        {{ $allocation->inventoryItem?->code ?: '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $allocation->inventoryItem?->name ?: '-' }}
                                    </p>
                                </td>

                                <td class="px-3 py-4 text-sm">
                                    @if ($allocation->tracking_type === 'serialized')
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 font-semibold text-gray-800">
                                            {{ $allocation->inventoryAsset?->asset_code ?: '-' }}
                                        </span>
                                    @else
                                        <span class="text-gray-600 dark:text-gray-300">
                                            Quantity Stock
                                        </span>
                                    @endif
                                </td>

                                <td class="px-3 py-4 text-right text-sm font-semibold text-gray-800 dark:text-white">
                                    {{ $formatQty($allocation->quantity) }}
                                    {{ $allocation->inventoryItem?->unit ?: $allocation->deliveryOrderItem?->unit }}
                                </td>

                                <td class="px-3 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClass($allocation->status) }}">
                                        {{ strtoupper($allocation->status) }}
                                    </span>
                                </td>

                                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($allocation->picked_at)
                                        {{ $allocation->picked_at->format('d M Y H:i') }}

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $allocation->pickedBy?->name ?: '-' }}
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td class="px-3 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($allocation->out_at)
                                        {{ $allocation->out_at->format('d M Y H:i') }}

                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $allocation->outBy?->name ?: '-' }}
                                        </div>
                                    @else
                                        -
                                    @endif
                                </td>

                                <td class="px-3 py-4 text-right">
                                    @if (
                                        $canOperate
                                        && $allocation->status === 'allocated'
                                        && bouncer()->hasPermission('delivery-orders.picking')
                                    )
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'admin.delivery-orders.picking.mark',
                                                [
                                                    $deliveryOrder->id,
                                                    $allocation->id,
                                                ]
                                            ) }}"
                                        >
                                            @csrf
                                            @method('PUT')

                                            <button
                                                type="submit"
                                                class="secondary-button"
                                                onclick="return confirm('Tandai item ini sebagai PICKED?')"
                                            >
                                                Mark Picked
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">
                                            -
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($allOut)
            <div class="mt-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                Semua inventory sudah OUT. Surat Jalan sekarang dapat diproses ke Mark as Delivered.
            </div>
        @elseif (
            $summary['picked'] > 0
            && $summary['allocated'] > 0
        )
            <div class="mt-4 rounded-md border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-700">
                Picking belum lengkap. Confirm OUT baru tersedia setelah seluruh allocation berstatus PICKED.
            </div>
        @endif
    </div>
</x-admin::layouts>
