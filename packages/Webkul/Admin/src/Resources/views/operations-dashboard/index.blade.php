<x-admin::layouts>
    <x-slot:title>Operations Dashboard</x-slot>

    <div class="flex flex-col gap-4">
        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-bold uppercase text-gray-500">Role-Based Dashboard</div>
            <h1 class="mt-1 text-2xl font-bold">Operations Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">
                Current role: <strong>{{ $role }}</strong>
            </p>
        </div>

        <div
            style="
                display:grid;
                grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
                gap:14px;
            "
        >
            @foreach ($cards as $card)
                @if ($card['url'])
                    <a href="{{ $card['url'] }}" class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @else
                    <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @endif
                        <div class="text-xs font-bold uppercase text-gray-500">{{ $card['label'] }}</div>
                        <div class="mt-2 text-2xl font-bold">{{ $card['value'] }}</div>
                        <div class="mt-2 text-sm text-gray-500">{{ $card['hint'] }}</div>
                @if ($card['url'])
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </div>

        <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-bold">Quick Controls</h2>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="secondary-button">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-admin::layouts>
