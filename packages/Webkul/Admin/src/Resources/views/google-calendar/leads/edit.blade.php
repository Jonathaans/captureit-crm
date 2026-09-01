<x-admin::layouts>
    <x-slot:title>
        Confirmed Event Calendar
    </x-slot>

    <div
        style="
            width:100%;
            max-width:980px;
            margin:0 auto;
            display:flex;
            flex-direction:column;
            gap:14px;
        "
    >
        <section
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    gap:16px;
                    align-items:flex-start;
                    flex-wrap:wrap;
                "
            >
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                        Lead WON → Confirmed Event
                    </p>

                    <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                        Google Calendar Event
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Lead #{{ $lead->id }}
                    </p>
                </div>

                <a
                    href="{{ route(
                        'admin.leads.view',
                        $lead->id
                    ) }}"
                    class="secondary-button"
                >
                    Back to Lead
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                    gap:10px;
                "
            >
                <div>
                    <p class="text-[11px] font-bold uppercase text-gray-500">
                        Google Sync
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ strtoupper($event->sync_status) }}
                    </p>
                </div>

                <div>
                    <p class="text-[11px] font-bold uppercase text-gray-500">
                        Activity
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ $event->activity_id
                            ? 'Activity #'.$event->activity_id
                            : 'Pending / Adaptive Bridge' }}
                    </p>
                </div>

                <div>
                    <p class="text-[11px] font-bold uppercase text-gray-500">
                        Integration
                    </p>

                    <p class="mt-1 font-semibold">
                        {{ $googleEnabled
                            ? 'ENABLED'
                            : 'NOT CONFIGURED' }}
                    </p>
                </div>
            </div>

            @if ($event->sync_error)
                <p class="mt-3 text-xs text-red-600">
                    Google: {{ $event->sync_error }}
                </p>
            @endif

            @if ($event->activity_sync_error)
                <p class="mt-2 text-xs text-amber-700">
                    Activity: {{ $event->activity_sync_error }}
                </p>
            @endif
        </section>

        <form
            method="POST"
            action="{{ route(
                'admin.google-calendar.leads.update',
                $lead->id
            ) }}"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
                    gap:14px;
                "
            >
                <div style="grid-column:1/-1;">
                    <label class="mb-1.5 block text-sm font-bold">
                        Event Title *
                    </label>

                    <input
                        type="text"
                        name="title"
                        value="{{ old(
                            'title',
                            $event->title
                        ) }}"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-bold">
                        Sales Owner *
                    </label>

                    <select
                        name="sales_owner_id"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                        <option value="">
                            Select Sales Owner
                        </option>

                        @foreach ($salesOwners as $salesOwner)
                            <option
                                value="{{ $salesOwner->id }}"
                                @selected(
                                    (string) old(
                                        'sales_owner_id',
                                        $event->sales_owner_id
                                    ) === (string) $salesOwner->id
                                )
                            >
                                {{ $salesOwner->name }}
                                ({{ $salesOwner->role?->name }})
                                · Color {{ $salesOwner->google_calendar_color_id ?: 'AUTO' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-bold">
                        Location
                    </label>

                    <input
                        type="text"
                        name="location"
                        value="{{ old(
                            'location',
                            $event->location
                        ) }}"
                        class="w-full rounded-md border px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-bold">
                        Start *
                    </label>

                    <input
                        type="datetime-local"
                        name="start_at"
                        value="{{ old(
                            'start_at',
                            $event->start_at?->format(
                                'Y-m-d\TH:i'
                            )
                        ) }}"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-bold">
                        End *
                    </label>

                    <input
                        type="datetime-local"
                        name="end_at"
                        value="{{ old(
                            'end_at',
                            $event->end_at?->format(
                                'Y-m-d\TH:i'
                            )
                        ) }}"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                </div>

                <div style="grid-column:1/-1;">
                    <label class="mb-1.5 block text-sm font-bold">
                        Notes
                    </label>

                    <textarea
                        name="notes"
                        rows="5"
                        class="w-full rounded-md border px-3 py-2"
                    >{{ old(
                        'notes',
                        $event->notes
                    ) }}</textarea>
                </div>
            </div>

            <div
                class="mt-5"
                style="
                    display:flex;
                    justify-content:flex-end;
                    gap:10px;
                    flex-wrap:wrap;
                "
            >
                @if ($event->start_at)
                    <button
                        type="submit"
                        formaction="{{ route(
                            'admin.google-calendar.leads.sync',
                            $lead->id
                        ) }}"
                        class="secondary-button"
                    >
                        Sync Again
                    </button>
                @endif

                <button
                    type="submit"
                    class="primary-button"
                >
                    Save Confirmed Event
                </button>
            </div>
        </form>
    </div>
</x-admin::layouts>
