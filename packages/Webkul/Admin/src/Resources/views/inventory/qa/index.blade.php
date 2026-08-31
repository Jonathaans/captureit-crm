<x-admin::layouts>
    <x-slot:title>
        Warehouse QA
    </x-slot>

    @php
        $statusStyle = static function ($status) {
            return match ($status) {
                'pass' => [
                    'badge' => 'bg-emerald-100 text-emerald-700',
                    'icon' => '✓',
                    'border' => '#bbf7d0',
                    'background' => '#f0fdf4',
                ],
                'warn' => [
                    'badge' => 'bg-amber-100 text-amber-700',
                    'icon' => '!',
                    'border' => '#fde68a',
                    'background' => '#fffbeb',
                ],
                default => [
                    'badge' => 'bg-red-100 text-red-700',
                    'icon' => '!',
                    'border' => '#fecaca',
                    'background' => '#fef2f2',
                ],
            };
        };
    @endphp

    <div
        style="
            width:100%;
            max-width:1680px;
            margin:0 auto;
            display:flex;
            flex-direction:column;
            gap:18px;
        "
    >
        <div
            style="
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:16px;
                flex-wrap:wrap;
            "
        >
            <div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    End-to-End Warehouse QA
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Read-only integrity audit untuk Inventory, SJ, Return, Maintenance, Stock Opname, dan Movement.
                </p>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a
                    href="{{ route('admin.inventory.dashboard') }}"
                    class="secondary-button"
                >
                    Dashboard
                </a>

                <a
                    href="{{ route('admin.inventory.qa.export-csv') }}"
                    class="secondary-button"
                >
                    Export QA CSV
                </a>

                <a
                    href="{{ route('admin.inventory.qa.index') }}"
                    class="primary-button"
                >
                    Run QA Again
                </a>
            </div>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
                gap:12px;
            "
        >
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                    QA Health
                </p>
                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ $summary['health_percent'] }}%
                </p>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full {{ $summary['fail'] > 0 ? 'bg-red-500' : ($summary['warn'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                        style="width:{{ $summary['health_percent'] }}%;"
                    ></div>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">
                    Pass
                </p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">
                    {{ $summary['pass'] }}
                </p>
                <p class="mt-2 text-xs text-emerald-700">
                    Integrity check aman
                </p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">
                    Warning
                </p>
                <p class="mt-2 text-3xl font-bold text-amber-800">
                    {{ $summary['warn'] }}
                </p>
                <p class="mt-2 text-xs text-amber-700">
                    Tidak memblokir, perlu review
                </p>
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-red-700">
                    Fail
                </p>
                <p class="mt-2 text-3xl font-bold text-red-800">
                    {{ $summary['fail'] }}
                </p>
                <p class="mt-2 text-xs text-red-700">
                    Perlu diperbaiki sebelum production
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                <p class="font-bold text-gray-900 dark:text-white">
                    Automated Integrity Checks
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Audit ini tidak mengubah database. Refresh / Run QA Again akan menghitung ulang kondisi terbaru.
                </p>
            </div>

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(360px,1fr));
                    gap:12px;
                    padding:14px;
                "
            >
                @foreach ($checks as $check)
                    @php
                        $tone = $statusStyle($check['status']);
                    @endphp

                    <div
                        style="
                            border:1px solid {{ $tone['border'] }};
                            background:{{ $tone['background'] }};
                            border-radius:12px;
                            padding:16px;
                            min-width:0;
                        "
                    >
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <span
                                style="
                                    width:36px;
                                    height:36px;
                                    min-width:36px;
                                    border-radius:10px;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    font-weight:800;
                                    background:#ffffff;
                                    border:1px solid {{ $tone['border'] }};
                                "
                            >
                                {{ $tone['icon'] }}
                            </span>

                            <div style="min-width:0;flex:1;">
                                <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                    <div>
                                        <p class="text-sm font-bold text-gray-900">
                                            {{ $check['name'] }}
                                        </p>

                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ $check['category'] }}
                                        </p>
                                    </div>

                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase {{ $tone['badge'] }}">
                                        {{ $check['status'] }}
                                    </span>
                                </div>

                                <p class="mt-3 text-sm leading-5 text-gray-700">
                                    {{ $check['summary'] }}
                                </p>

                                @if ($check['count'] > 0)
                                    <p class="mt-2 text-xs font-bold text-gray-700">
                                        Affected: {{ $check['count'] }}
                                    </p>
                                @endif

                                <div class="mt-3 rounded-lg bg-white/80 p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Recommendation
                                    </p>
                                    <p class="mt-1 text-xs leading-5 text-gray-600">
                                        {{ $check['recommendation'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div>
                        <p class="font-bold text-gray-900 dark:text-white">
                            Manual End-to-End Acceptance Test
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Jalankan menggunakan satu project/event test. Kolom Expected Result adalah acceptance criteria.
                        </p>
                    </div>

                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                        12 Steps
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table style="width:100%;min-width:980px;border-collapse:collapse;">
                    <thead class="border-b border-gray-200 bg-gray-50 text-left text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/30">
                        <tr>
                            <th class="px-5 py-3">Step</th>
                            <th class="px-4 py-3">Test</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Expected Result</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($manualFlow as $step)
                            <tr class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                                <td class="px-5 py-4">
                                    <span
                                        style="
                                            width:32px;
                                            height:32px;
                                            border-radius:9px;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            background:#fff7ed;
                                            color:#c2410c;
                                            font-weight:800;
                                        "
                                    >
                                        {{ $step['step'] }}
                                    </span>
                                </td>

                                <td class="px-4 py-4">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $step['title'] }}
                                    </p>
                                </td>

                                <td class="px-4 py-4 text-sm leading-5 text-gray-600 dark:text-gray-300">
                                    {{ $step['action'] }}
                                </td>

                                <td class="px-4 py-4">
                                    <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm leading-5 text-emerald-800">
                                        {{ $step['expected'] }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">
            <p class="font-bold text-blue-900">
                Production Gate
            </p>

            <p class="mt-2 text-sm leading-6 text-blue-800">
                Target sebelum data operasional sungguhan masuk: automated check tidak memiliki FAIL, warning sudah dipahami/diterima, dan 12 manual acceptance steps lulus pada satu event test lengkap.
            </p>
        </div>
    </div>
</x-admin::layouts>
