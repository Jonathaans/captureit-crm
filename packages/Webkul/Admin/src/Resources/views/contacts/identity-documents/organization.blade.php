<x-admin::layouts>
    <x-slot:title>
        NPWP - {{ $organization->name }}
    </x-slot>

    <div
        style="
            width:100%;
            max-width:900px;
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
                    align-items:flex-start;
                    gap:16px;
                    flex-wrap:wrap;
                "
            >
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        NPWP Document
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        {{ $organization->name }}
                    </p>
                </div>

                <a
                    href="{{ route(
                        'admin.contacts.organizations.edit',
                        $organization->id
                    ) }}"
                    class="secondary-button"
                >
                    Back to Organization
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
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
                    display:flex;
                    justify-content:space-between;
                    gap:12px;
                    align-items:center;
                    flex-wrap:wrap;
                "
            >
                <div>
                    <h2 class="font-bold text-gray-900 dark:text-white">
                        Current NPWP
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        Dokumen tersimpan private dan hanya dibuka melalui route admin.
                    </p>
                </div>

                @if (! empty($organization->npwp_image_path))
                    <a
                        href="{{ route(
                            'admin.contacts.organizations.npwp',
                            $organization->id
                        ) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="secondary-button"
                    >
                        View Current NPWP
                    </a>
                @else
                    <span class="text-sm font-semibold text-gray-400">
                        No NPWP uploaded
                    </span>
                @endif
            </div>
        </section>

        <form
            method="POST"
            action="{{ route(
                'admin.contacts.organizations.identity.update',
                $organization->id
            ) }}"
            enctype="multipart/form-data"
            class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf

            <label class="mb-1.5 block text-sm font-bold text-gray-800 dark:text-white">
                {{ ! empty($organization->npwp_image_path)
                    ? 'Replace NPWP Image'
                    : 'Upload NPWP Image' }}
            </label>

            <input
                type="file"
                name="npwp_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950"
                required
            >

            <p class="mt-2 text-xs text-gray-500">
                JPG, JPEG, PNG, atau WEBP. Maksimal 5 MB.
            </p>

            <div class="mt-5 flex justify-end">
                <button
                    type="submit"
                    class="primary-button"
                >
                    Save NPWP
                </button>
            </div>
        </form>
    </div>
</x-admin::layouts>
