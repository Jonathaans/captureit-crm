<x-admin::layouts>
    <x-slot:title>
        Inventory Alerts & Reorder
    </x-slot>

    @php
        $tabs = [
            'all' => 'All Alerts',
            'stock' => 'Stock Alerts',
            'assets' => 'Asset Issues',
            'returns' => 'Returns',
            'reorder' => 'Reorder',
        ];

        $severityClass = static function ($severity) {
            return match ($severity) {
                'critical' => 'bg-red-100 text-red-700 ring-red-200',
                'warning' => 'bg-amber-100 text-amber-700 ring-amber-200',
                'info' => 'bg-blue-100 text-blue-700 ring-blue-200',
                default => 'bg-gray-100 text-gray-700 ring-gray-200',
            };
        };

        $typeTone = static function ($type) {
            return match ($type) {
                'critical_stock', 'missing_asset', 'no_available_assets' => [
                    'icon' => 'bg-red-50 text-red-600 ring-red-100',
                    'dot' => 'bg-red-500',
                ],
                'low_stock', 'damaged_asset', 'return_pending', 'no_registered_assets' => [
                    'icon' => 'bg-amber-50 text-amber-600 ring-amber-100',
                    'dot' => 'bg-amber-500',
                ],
                'maintenance_asset' => [
                    'icon' => 'bg-violet-50 text-violet-600 ring-violet-100',
                    'dot' => 'bg-violet-500',
                ],
                default => [
                    'icon' => 'bg-blue-50 text-blue-600 ring-blue-100',
                    'dot' => 'bg-blue-500',
                ],
            };
        };

        $formatQty = static function ($value) {
            return rtrim(
                rtrim(
                    number_format(
                        (float) $value,
                        2,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            );
        };
    @endphp

    <div class="grid gap-5">
        {{-- Hero --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-amber-100/70 blur-3xl dark:bg-amber-900/20"></div>
            <div class="pointer-events-none absolute right-20 top-8 h-32 w-32 rounded-full bg-blue-100/60 blur-3xl dark:bg-blue-900/20"></div>

            <div class="relative flex items-start justify-between gap-5 max-lg:flex-wrap">
                <div class="max-w-3xl">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Warehouse Intelligence
                        </span>

                        @if ($summary['critical'] > 0)
                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 ring-1 ring-inset ring-red-200">
                                {{ $summary['critical'] }} critical
                            </span>
                        @endif
                    </div>

                    <p class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Inventory Alerts & Reorder
                    </p>

                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Monitor stock minimum, critical quantity, missing assets, maintenance, return pending, dan availability risk dari satu layar.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.inventory.dashboard') }}"
                        class="secondary-button"
                    >
                        Dashboard
                    </a>

                    <a
                        href="{{ route('admin.inventory.alerts.export-csv') }}"
                        class="secondary-button"
                    >
                        Export CSV
                    </a>

                    @if (bouncer()->hasPermission('inventory.stock-opname'))
                        <a
                            href="{{ route('admin.inventory.stock-opname.index') }}"
                            class="primary-button"
                        >
                            Stock Opname
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-6 gap-3 max-2xl:grid-cols-3 max-lg:grid-cols-2 max-sm:grid-cols-1">
            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'stock']) }}"
                class="group rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-amber-900 dark:from-amber-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 ring-1 ring-amber-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 7h16M6 7l1 12h10l1-12M9 11v4M15 11v4M9 4h6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-amber-700">
                        View
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-amber-700">
                    Low Stock
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['low_stock'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    at / below minimum
                </p>
            </a>

            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'stock']) }}"
                class="group rounded-2xl border border-red-200 bg-gradient-to-br from-red-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-red-900 dark:from-red-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-100 text-red-700 ring-1 ring-red-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 8v5m0 3h.01M10.3 4.7 3.7 17a2 2 0 0 0 1.76 3h13.08a2 2 0 0 0 1.76-3L13.7 4.7a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-red-700">
                        Critical
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-red-700">
                    Critical Stock
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['critical_stock'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    quantity reached zero
                </p>
            </a>

            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'assets']) }}"
                class="group rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-blue-900 dark:from-blue-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 text-blue-700 ring-1 ring-blue-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m12 3 8 4-8 4-8-4 8-4Zm8 4v10l-8 4-8-4V7m8 4v10" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-blue-700">
                        Assets
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-blue-700">
                    Missing Assets
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['missing'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    physical asset missing
                </p>
            </a>

            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'assets']) }}"
                class="group rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-violet-900 dark:from-violet-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700 ring-1 ring-violet-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M14.7 6.3a4 4 0 0 0-5.6 5.6l-5.4 5.4a2 2 0 1 0 2.8 2.8l5.4-5.4a4 4 0 0 0 5.6-5.6l-2.4 2.4-2.6-2.6 2.2-2.6Z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-violet-700">
                        Repair
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-violet-700">
                    Maintenance
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['maintenance'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    currently in repair
                </p>
            </a>

            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'returns']) }}"
                class="group rounded-2xl border border-cyan-200 bg-gradient-to-br from-cyan-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-cyan-900 dark:from-cyan-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M7 7h10a3 3 0 0 1 3 3v7H8m0 0 3-3m-3 3 3 3M4 4v9" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-cyan-700">
                        Return
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-cyan-700">
                    Return Pending
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['return_pending'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    awaiting inspection
                </p>
            </a>

            <a
                href="{{ route('admin.inventory.alerts.index', ['tab' => 'reorder']) }}"
                class="group rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-emerald-900 dark:from-emerald-950/20 dark:to-gray-900"
            >
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 5h2l2 10h9l2-7H7m3 11h.01M17 19h.01" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>

                    <span class="text-xs font-bold text-emerald-700">
                        Suggest
                    </span>
                </div>

                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-emerald-700">
                    Reorder
                </p>

                <p class="mt-1 text-3xl font-black text-gray-900 dark:text-white">
                    {{ $summary['reorder'] }}
                </p>

                <p class="mt-2 text-xs text-gray-500">
                    suggested restocks
                </p>
            </a>
        </div>

        {{-- Tabs + Search --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 max-xl:flex-wrap dark:border-gray-800">
                <div class="flex flex-wrap gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
                    @foreach ($tabs as $key => $label)
                        <a
                            href="{{ route(
                                'admin.inventory.alerts.index',
                                array_filter([
                                    'tab' => $key,
                                    'search' => $search ?: null,
                                ])
                            ) }}"
                            class="rounded-lg px-4 py-2 text-sm font-bold transition {{ $tab === $key ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white' }}"
                        >
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <form
                    method="GET"
                    action="{{ route('admin.inventory.alerts.index') }}"
                    class="flex min-w-[320px] items-center gap-2 max-sm:min-w-0 max-sm:w-full"
                >
                    <input
                        type="hidden"
                        name="tab"
                        value="{{ $tab }}"
                    >

                    <div class="relative flex-1">
                        <svg
                            viewBox="0 0 24 24"
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5"></path>
                        </svg>

                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search item, asset, code..."
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-10 pr-3 text-sm text-gray-800 outline-none transition focus:border-brandColor focus:bg-white dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        >
                    </div>

                    <button
                        type="submit"
                        class="secondary-button"
                    >
                        Search
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-[minmax(0,1fr)_380px] max-2xl:grid-cols-1">
                {{-- Alerts Table --}}
                <div class="min-w-0 border-r border-gray-200 dark:border-gray-800 max-2xl:border-r-0">
                    <div class="flex items-center justify-between px-5 py-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-gray-900 dark:text-white">
                                    Active Alerts
                                </p>

                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-black text-red-700">
                                    {{ $filteredAlerts->count() }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-gray-500">
                                Current operational state. Alerts disappear automatically after the underlying problem is resolved.
                            </p>
                        </div>
                    </div>

                    @if ($filteredAlerts->isEmpty())
                        <div class="flex min-h-[360px] flex-col items-center justify-center px-6 py-12 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                                <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            <p class="mt-4 font-bold text-gray-900 dark:text-white">
                                No alerts in this view
                            </p>

                            <p class="mt-1 max-w-md text-sm text-gray-500">
                                Inventory sedang sehat untuk filter ini. Sebuah kejadian langka, nikmati selagi berlangsung.
                            </p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-y border-gray-200 bg-gray-50/80 text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/40">
                                    <tr>
                                        <th class="px-5 py-3">Alert</th>
                                        <th class="px-4 py-3">Item / Asset</th>
                                        <th class="px-4 py-3">Current</th>
                                        <th class="px-4 py-3">Severity</th>
                                        <th class="px-4 py-3">Recommended Action</th>
                                        <th class="px-5 py-3 text-right">Updated</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($filteredAlerts as $alert)
                                        @php
                                            $tone = $typeTone($alert['type']);
                                        @endphp

                                        <tr class="border-b border-gray-100 transition hover:bg-gray-50/70 last:border-b-0 dark:border-gray-800 dark:hover:bg-gray-800/40">
                                            <td class="px-5 py-4">
                                                <div class="flex items-center gap-3">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tone['icon'] }}">
                                                        @if (in_array($alert['type'], ['critical_stock', 'low_stock'], true))
                                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                <path d="M4 7h16M6 7l1 12h10l1-12M9 11v4M15 11v4M9 4h6" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        @elseif ($alert['type'] === 'maintenance_asset')
                                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                <path d="M14.7 6.3a4 4 0 0 0-5.6 5.6l-5.4 5.4a2 2 0 1 0 2.8 2.8l5.4-5.4a4 4 0 0 0 5.6-5.6l-2.4 2.4-2.6-2.6 2.2-2.6Z" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        @elseif ($alert['type'] === 'return_pending')
                                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                <path d="M7 7h10a3 3 0 0 1 3 3v7H8m0 0 3-3m-3 3 3 3M4 4v9" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        @else
                                                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                                                <path d="m12 3 8 4-8 4-8-4 8-4Zm8 4v10l-8 4-8-4V7m8 4v10" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        @endif
                                                    </span>

                                                    <div>
                                                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                                                            {{ $alert['label'] }}
                                                        </p>

                                                        <p class="mt-0.5 text-xs text-gray-500">
                                                            {{ $alert['warehouse'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-4 py-4">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {{ $alert['item_name'] }}
                                                </p>

                                                <p class="mt-0.5 font-mono text-xs text-gray-500">
                                                    {{ $alert['code'] }}
                                                </p>
                                            </td>

                                            <td class="px-4 py-4">
                                                <p class="text-sm font-bold text-gray-900 dark:text-white">
                                                    {{ $alert['current'] }}
                                                </p>

                                                <p class="mt-0.5 text-xs text-gray-500">
                                                    {{ $alert['detail'] }}
                                                </p>
                                            </td>

                                            <td class="px-4 py-4">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black uppercase ring-1 ring-inset {{ $severityClass($alert['severity']) }}">
                                                    {{ $alert['severity'] }}
                                                </span>
                                            </td>

                                            <td class="px-4 py-4">
                                                <p class="max-w-xs text-sm font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ $alert['recommended_action'] }}
                                                </p>

                                                <div class="mt-2">
                                                    @if ($alert['entity_type'] === 'asset')
                                                        <a
                                                            href="{{ route('admin.inventory.assets.edit', $alert['entity_id']) }}"
                                                            class="text-xs font-bold text-brandColor hover:underline"
                                                        >
                                                            Open Asset
                                                        </a>
                                                    @elseif ($alert['entity_type'] === 'consumable')
                                                        <a
                                                            href="{{ route('admin.inventory.consumables.edit', $alert['entity_id']) }}"
                                                            class="text-xs font-bold text-brandColor hover:underline"
                                                        >
                                                            Open Consumable
                                                        </a>
                                                    @elseif ($alert['entity_type'] === 'item')
                                                        <a
                                                            href="{{ route('admin.inventory.items.edit', $alert['entity_id']) }}"
                                                            class="text-xs font-bold text-brandColor hover:underline"
                                                        >
                                                            Open Item
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="whitespace-nowrap px-5 py-4 text-right text-xs text-gray-500">
                                                {{ $alert['updated_at']?->format('d M Y H:i') ?: '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Attention panel --}}
                <aside class="min-w-0">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-bold text-gray-900 dark:text-white">
                                    Attention Needed
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Highest priority operational risks.
                                </p>
                            </div>

                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50 text-red-600 ring-1 ring-red-100">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($attention as $alert)
                            @php
                                $tone = $typeTone($alert['type']);
                            @endphp

                            <div class="flex gap-3 px-5 py-4">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $tone['dot'] }}"></span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $alert['code'] }}
                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        {{ $alert['recommended_action'] }}
                                    </p>
                                </div>

                                <span class="whitespace-nowrap text-[11px] text-gray-400">
                                    {{ $alert['updated_at']?->diffForHumans() ?: '-' }}
                                </span>
                            </div>
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-gray-500">
                                No urgent attention needed.
                            </div>
                        @endforelse
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50/60 px-5 py-4 dark:border-gray-800 dark:bg-gray-950/30">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M9 18h6M10 22h4M8.5 14.5A6 6 0 1 1 15.5 14.5c-.9.7-1.5 1.5-1.5 2.5h-4c0-1-.6-1.8-1.5-2.5Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>

                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">
                                    Smart Reorder Rule
                                </p>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    Suggestion targets 2× minimum stock, so restock creates a practical buffer instead of merely crawling back to the threshold.
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        {{-- Reorder Suggestions --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-5 max-sm:flex-wrap dark:border-gray-800">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="font-bold text-gray-900 dark:text-white">
                            Reorder Suggestions
                        </p>

                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">
                            {{ $reorderSuggestions->count() }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs text-gray-500">
                        Quantity items at or below minimum stock. Suggested quantity targets 2× minimum stock.
                    </p>
                </div>

                @if (bouncer()->hasPermission('inventory.movements.adjust-stock'))
                    <a
                        href="{{ route('admin.inventory.movements.adjust-stock.create') }}"
                        class="secondary-button"
                    >
                        Open Stock Adjustment
                    </a>
                @endif
            </div>

            @if ($reorderSuggestions->isEmpty())
                <div class="p-8 text-center text-sm text-gray-500">
                    Tidak ada reorder suggestion. Quantity stock berada di atas minimum.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-gray-200 bg-gray-50/80 text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/40">
                            <tr>
                                <th class="px-5 py-3">Item</th>
                                <th class="px-4 py-3">Warehouse</th>
                                <th class="px-4 py-3">Minimum</th>
                                <th class="px-4 py-3">Current</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">Suggested Qty</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($reorderSuggestions as $suggestion)
                                <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">
                                            {{ $suggestion['name'] }}
                                        </p>

                                        <p class="mt-0.5 font-mono text-xs text-gray-500">
                                            {{ $suggestion['code'] }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $suggestion['warehouse'] }}
                                    </td>

                                    <td class="px-4 py-4 text-sm font-semibold">
                                        {{ $formatQty($suggestion['minimum']) }} {{ $suggestion['unit'] }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="text-sm font-black {{ $suggestion['severity'] === 'critical' ? 'text-red-600' : 'text-amber-600' }}">
                                            {{ $formatQty($suggestion['current']) }} {{ $suggestion['unit'] }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 text-sm font-semibold">
                                        {{ $formatQty($suggestion['target']) }} {{ $suggestion['unit'] }}
                                    </td>

                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-black text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                            +{{ $formatQty($suggestion['suggested']) }} {{ $suggestion['unit'] }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <a
                                            href="{{ route('admin.inventory.consumables.edit', $suggestion['id']) }}"
                                            class="text-xs font-bold text-brandColor hover:underline"
                                        >
                                            Open Item
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Rules --}}
        <div class="grid grid-cols-3 gap-3 max-lg:grid-cols-1">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-bold text-gray-900 dark:text-white">
                    Quantity Rule
                </p>

                <p class="mt-2 text-xs leading-5 text-gray-500">
                    LOW STOCK saat current ≤ minimum. CRITICAL saat current ≤ 0. Reorder target menggunakan 2× minimum stock.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-bold text-gray-900 dark:text-white">
                    Serialized Rule
                </p>

                <p class="mt-2 text-xs leading-5 text-gray-500">
                    MISSING, DAMAGED, MAINTENANCE, RETURN PENDING, serta item tanpa unit AVAILABLE muncul otomatis sebagai alert.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-bold text-gray-900 dark:text-white">
                    Live State
                </p>

                <p class="mt-2 text-xs leading-5 text-gray-500">
                    Alert dihitung dari kondisi inventory saat halaman dibuka. Tidak ada tabel alert terpisah yang bisa basi atau tertinggal sinkronisasi.
                </p>
            </div>
        </div>
    </div>
</x-admin::layouts>
