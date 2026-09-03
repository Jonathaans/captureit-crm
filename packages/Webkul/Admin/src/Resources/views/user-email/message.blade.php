<x-admin::layouts>
    <x-slot:title>
        {{ $message->subject ?: 'Email' }}
    </x-slot>

    <div class="mx-auto flex max-w-5xl flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-xl font-bold">
                    {{ $message->subject ?: '(No Subject)' }}
                </h1>

                <div class="mt-2 text-sm text-gray-500">
                    From:
                    {{ $message->from_name ?: '-' }}
                    &lt;{{ $message->from_email ?: '-' }}&gt;
                </div>

                <div class="text-sm text-gray-500">
                    Received:
                    {{ $message->received_at?->format('Y-m-d H:i:s') ?: '-' }}
                </div>
            </div>

                        <!-- USER EMAIL V1.2 REPLY BUTTONS -->
            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.compose', ['reply_to' => $message->id, 'mode' => 'reply']) }}"
                    class="primary-button"
                >
                    Reply
                </a>

                <a
                    href="{{ route('admin.my-email.compose', ['reply_to' => $message->id, 'mode' => 'reply_all']) }}"
                    class="secondary-button"
                >
                    Reply All
                </a>
            </div>
<a
                href="{{ route('admin.my-email.inbox') }}"
                class="secondary-button"
            >
                Back
            </a>
        </div>

        <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @if ($message->html_body)
                <iframe
                    title="Email content"
                    sandbox=""
                    style="
                        width:100%;
                        min-height:600px;
                        border:0;
                        background:white;
                    "
                    srcdoc="{{ $message->html_body }}"
                ></iframe>
            @else
                <pre class="whitespace-pre-wrap font-sans text-sm">{{ $message->text_body }}</pre>
            @endif
        </div>
    
        <!-- USER EMAIL V1.3 ATTACHMENT LIST -->
        @php
            $attachments = \Webkul\Admin\Models\UserEmailAttachment::query()
                ->where('user_id', auth()->guard('user')->id())
                ->where('message_id', $message->id)
                ->get();
        @endphp

        @if ($attachments->isNotEmpty())
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-bold">
                    Attachments ({{ $attachments->count() }})
                </h2>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($attachments as $attachment)
                        <a
                            href="{{ route('admin.my-email.attachments.download', $attachment->id) }}"
                            class="secondary-button"
                        >
                            {{ $attachment->original_name }}
                            ({{ number_format($attachment->size / 1024, 1) }} KB)
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <form
                method="POST"
                action="{{ route('admin.my-email.trash.move', $message->id) }}"
            >
                @csrf
                <button class="secondary-button">
                    Move to Trash
                </button>
            </form>
        </div>
</div>

{{-- MY EMAIL DOUBLE ESCAPE FIX V5.1 --}}
</x-admin::layouts>
