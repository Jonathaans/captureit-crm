<x-admin::layouts>
    <x-slot:title>
        {{ $replyTo ? 'Reply Email' : 'Compose Email' }}
    </x-slot>

    <div class="mx-auto flex max-w-5xl flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <div class="text-xs font-bold uppercase text-gray-500">
                    {{ $replyTo ? 'Reply' : 'New Message' }}
                </div>

                <h1 class="mt-1 text-2xl font-bold">
                    {{ $replyTo ? 'Reply Email' : 'Compose Email' }}
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    From: <strong>{{ $account->email_address }}</strong>
                </p>
            </div>

            <div class="flex gap-2">
                <a
                    href="{{ route('admin.my-email.inbox') }}"
                    class="secondary-button"
                >
                    Inbox
                </a>

                <a
                    href="{{ route('admin.my-email.sent') }}"
                    class="secondary-button"
                >
                    Sent
                </a>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('admin.my-email.send') }}"
            class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf

            @if ($replyTo)
                <input
                    type="hidden"
                    name="reply_to_message_id"
                    value="{{ $replyTo->id }}"
                >
            @endif

            <div class="flex flex-col gap-4">
                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        To *
                    </label>

                    <input
                        name="to"
                        value="{{ old('to', $to) }}"
                        class="w-full rounded-md border px-3 py-2"
                        placeholder="customer@example.com"
                        required
                    >

                    <div class="mt-1 text-xs text-gray-500">
                        Multiple addresses: separate with comma or semicolon.
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Cc
                    </label>

                    <input
                        name="cc"
                        value="{{ old('cc', $cc) }}"
                        class="w-full rounded-md border px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Subject *
                    </label>

                    <input
                        name="subject"
                        value="{{ old('subject', $subject) }}"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">
                        Message *
                    </label>

                    <textarea
                        name="message"
                        rows="18"
                        class="w-full rounded-md border px-3 py-2 font-sans"
                        required
                    >{{ old('message', $body) }}</textarea>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button class="primary-button">
                    Send Email
                </button>
            </div>
        </form>
    </div>
</x-admin::layouts>
