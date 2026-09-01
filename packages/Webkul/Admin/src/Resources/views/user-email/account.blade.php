<x-admin::layouts>
    <x-slot:title>
        My Email Integration
    </x-slot>

    <div class="mx-auto flex max-w-6xl flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <div class="text-xs font-bold uppercase text-gray-500">
                    Personal Mailbox
                </div>

                <h1 class="mt-1 text-2xl font-bold">
                    Email Integration
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    IMAP untuk menerima email, SMTP untuk mengirim email.
                    Credential hanya dipakai untuk akun user yang sedang login.
                </p>
            </div>

            <a
                href="{{ route('admin.my-email.inbox') }}"
                class="secondary-button"
            >
                My Inbox
            </a>
        </div>

        <form
            method="POST"
            action="{{ route('admin.my-email.settings.update') }}"
            class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf
            @method('PUT')

            <div
                style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
                    gap:16px;
                "
            >
                <div style="grid-column:1/-1;">
                    <label class="mb-1.5 block text-sm font-semibold">
                        Email Address *
                    </label>

                    <input
                        type="email"
                        name="email_address"
                        value="{{ old('email_address', $account->email_address) }}"
                        class="w-full rounded-md border px-3 py-2"
                        required
                    >
                </div>

                <div
                    class="rounded-lg border p-4"
                    style="grid-column:1/-1;"
                >
                    <h2 class="font-bold">
                        Incoming Mail / IMAP
                    </h2>

                    <div
                        class="mt-4"
                        style="
                            display:grid;
                            grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
                            gap:14px;
                        "
                    >
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                IMAP Host *
                            </label>

                            <input
                                name="imap_host"
                                value="{{ old('imap_host', $account->imap_host) }}"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="mail.example.com"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                IMAP Port *
                            </label>

                            <input
                                type="number"
                                name="imap_port"
                                value="{{ old('imap_port', $account->imap_port ?: 993) }}"
                                class="w-full rounded-md border px-3 py-2"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Encryption
                            </label>

                            <select
                                name="imap_encryption"
                                class="w-full rounded-md border px-3 py-2"
                            >
                                @foreach (['ssl', 'tls', 'none'] as $value)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('imap_encryption', $account->imap_encryption ?: 'ssl') === $value)
                                    >
                                        {{ strtoupper($value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                IMAP Username *
                            </label>

                            <input
                                name="imap_username"
                                value="{{ old('imap_username', $account->imap_username) }}"
                                class="w-full rounded-md border px-3 py-2"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                IMAP Password
                            </label>

                            <input
                                type="password"
                                name="imap_password"
                                value=""
                                autocomplete="new-password"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="{{ $account->exists ? 'Leave blank to keep current password' : 'Mailbox password' }}"
                            >
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="imap_validate_certificate"
                                value="1"
                                @checked(old('imap_validate_certificate', $account->imap_validate_certificate ?? true))
                            >

                            Validate SSL Certificate
                        </label>
                    </div>
                </div>

                <div
                    class="rounded-lg border p-4"
                    style="grid-column:1/-1;"
                >
                    <h2 class="font-bold">
                        Outgoing Mail / SMTP
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        SMTP username/password boleh dikosongkan untuk memakai credential IMAP.
                    </p>

                    <div
                        class="mt-4"
                        style="
                            display:grid;
                            grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
                            gap:14px;
                        "
                    >
                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                SMTP Host *
                            </label>

                            <input
                                name="smtp_host"
                                value="{{ old('smtp_host', $account->smtp_host) }}"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="mail.example.com"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                SMTP Port *
                            </label>

                            <input
                                type="number"
                                name="smtp_port"
                                value="{{ old('smtp_port', $account->smtp_port ?: 465) }}"
                                class="w-full rounded-md border px-3 py-2"
                                required
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                Encryption
                            </label>

                            <select
                                name="smtp_encryption"
                                class="w-full rounded-md border px-3 py-2"
                            >
                                @foreach (['ssl', 'tls', 'starttls', 'none'] as $value)
                                    <option
                                        value="{{ $value }}"
                                        @selected(old('smtp_encryption', $account->smtp_encryption ?: 'ssl') === $value)
                                    >
                                        {{ strtoupper($value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                SMTP Username
                            </label>

                            <input
                                name="smtp_username"
                                value="{{ old('smtp_username', $account->smtp_username) }}"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="Blank = use IMAP username"
                            >
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-semibold">
                                SMTP Password
                            </label>

                            <input
                                type="password"
                                name="smtp_password"
                                value=""
                                autocomplete="new-password"
                                class="w-full rounded-md border px-3 py-2"
                                placeholder="Blank = use IMAP password"
                            >
                        </div>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="sync_enabled"
                        value="1"
                        @checked(old('sync_enabled', $account->sync_enabled ?? true))
                    >

                    Enable automatic IMAP sync
                </label>
            </div>

            <div class="mt-5 flex flex-wrap justify-between gap-2">
                <div class="text-sm text-gray-500">
                    IMAP:
                    <strong>{{ strtoupper($account->imap_status ?: 'UNTESTED') }}</strong>

                    &nbsp; · &nbsp;

                    SMTP:
                    <strong>{{ strtoupper($account->smtp_status ?: 'UNTESTED') }}</strong>

                    @if ($account->last_synced_at)
                        &nbsp; · &nbsp;
                        Last Sync:
                        {{ $account->last_synced_at->format('Y-m-d H:i:s') }}
                    @endif
                </div>

                <button class="primary-button">
                    Save Email Connection
                </button>
            </div>
        </form>

        @if ($account->exists)
            <div class="flex flex-wrap gap-2">
                <form
                    method="POST"
                    action="{{ route('admin.my-email.test-imap') }}"
                >
                    @csrf

                    <button class="secondary-button">
                        Test IMAP
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('admin.my-email.test-smtp') }}"
                >
                    @csrf

                    <button class="secondary-button">
                        Test SMTP
                    </button>
                </form>
            </div>
        @endif

        @if ($account->last_sync_error)
            <div class="rounded-xl border p-4 text-sm">
                <strong>Last Error:</strong>
                {{ $account->last_sync_error }}
            </div>
        @endif
    </div>
</x-admin::layouts>
