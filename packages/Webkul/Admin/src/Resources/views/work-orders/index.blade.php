<x-admin::layouts>
    <x-slot:title>
        Surat Perintah Kerja
    </x-slot>

    @php
        $statusBadge = static function ($status) {
            return match (strtolower((string) $status)) {
                'released' => [
                    'label' => 'RELEASED',
                    'bg' => '#dbeafe',
                    'color' => '#1d4ed8',
                ],

                'completed' => [
                    'label' => 'COMPLETED',
                    'bg' => '#dcfce7',
                    'color' => '#15803d',
                ],

                'cancelled' => [
                    'label' => 'CANCELLED',
                    'bg' => '#fee2e2',
                    'color' => '#b91c1c',
                ],

                default => [
                    'label' => 'DRAFT',
                    'bg' => '#f3f4f6',
                    'color' => '#4b5563',
                ],
            };
        };
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-2xl font-bold">
                Surat Perintah Kerja
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Flow baru: Invoice -> SPK -> multiple Surat Jalan.
            </p>
        </div>

        <form
            method="GET"
            class="rounded-xl border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="flex gap-2">
                <input
                    name="search"
                    value="{{ $search }}"
                    class="w-full rounded-md border px-3 py-2"
                    placeholder="SPK / invoice / project / customer"
                >

                <button class="primary-button">
                    Search
                </button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="p-3">SPK</th>
                        <th class="p-3">Invoice / Project</th>
                        <th class="p-3">Customer</th>
                        <th class="p-3">Event</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Surat Jalan</th>
                        <th class="p-3 text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($workOrders as $workOrder)
                        @php
                            $badge = $statusBadge($workOrder->status);
                        @endphp

                        <tr class="border-b">
                            <td class="p-3">
                                <div class="font-bold">
                                    {{ $workOrder->work_order_number }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $workOrder->sales_person_name ?: '-' }}
                                </div>
                            </td>

                            <td class="p-3">
                                <div class="font-semibold">
                                    {{ $workOrder->invoice_number ?: '-' }}
                                </div>

                                <div class="text-xs text-gray-500">
                                    {{ $workOrder->project_code ?: '-' }}
                                    @if ($workOrder->project_name)
                                        · {{ $workOrder->project_name }}
                                    @endif
                                </div>
                            </td>

                            <td class="p-3">
                                {{ $workOrder->customer_name ?: '-' }}
                            </td>

                            <td class="p-3">
                                {{ $workOrder->event_date?->format('d M Y') ?: '-' }}
                            </td>

                            <td class="p-3">
                                <span
                                    style="
                                        display:inline-flex;
                                        padding:5px 9px;
                                        border-radius:9999px;
                                        background:{{ $badge['bg'] }};
                                        color:{{ $badge['color'] }};
                                        font-size:10px;
                                        font-weight:800;
                                    "
                                >
                                    {{ $badge['label'] }}
                                </span>
                            </td>

                            <td class="p-3 font-semibold">
                                {{ $workOrder->delivery_orders_count }}
                            </td>

                            <td class="p-3">
                                <div class="flex justify-end">
                                    <a
                                        href="{{ route('admin.work-orders.show', $workOrder->id) }}"
                                        class="secondary-button"
                                    >
                                        View
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="p-8 text-center text-gray-500"
                            >
                                Belum ada SPK.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $workOrders->links() }}
    </div>
</x-admin::layouts>
