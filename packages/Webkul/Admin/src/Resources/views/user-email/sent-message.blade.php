<x-admin::layouts>
    <x-slot:title>
        {{ $message->subject ?: 'Sent Email' }}
    </x-slot>

    @php
        $to = json_decode($message->to_emails ?: '[]', true) ?: [];
        $cc = json_decode($message->cc_emails ?: '[]', true) ?: [];

        $toLabel = collect($to)
            ->pluck('email')
            ->filter()
            ->implode(', ');

        $ccLabel = collect($cc)
            ->pluck('email')
            ->filter()
            ->implode(', ');
    @endphp

    <div class="mx-auto flex max-w-5xl flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-xl font-bold">
                    {{ $message->subject ?: '(No Subject)' }}
                </h1>

                <div class="mt-2 text-sm text-gray-500">
                    From: {{ $message->from_email }}
                </div>

                <div class="text-sm text-gray-500">
                    To: {{ $toLabel ?: '-' }}
                </div>

                @if ($ccLabel)
                    <div class="text-sm text-gray-500">
                        Cc: {{ $ccLabel }}
                    </div>
                @endif

                <div class="text-sm text-gray-500">
                    Sent:
                    {{ $message->sent_at?->format('Y-m-d H:i:s') ?: '-' }}
                </div>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.sent') }}"
                    class="secondary-button"
                >
                    Back to Sent
                </a>

                <a
                    href="{{ route('admin.my-email.compose') }}"
                    class="primary-button"
                >
                    + Compose
                </a>
            </div>
        </div>

        <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <pre class="whitespace-pre-wrap font-sans text-sm">{{ $message->text_body }}</pre>
        </div>
    </div>
</x-admin::layouts>
