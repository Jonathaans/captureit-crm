<x-admin::layouts>
    <x-slot:title>{{ $vendor->exists ? 'Edit Vendor' : 'Create Vendor' }}</x-slot>

    <div class="mx-auto flex max-w-5xl flex-col gap-4">
        <div class="flex items-center justify-between rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-2xl font-bold">{{ $vendor->exists ? 'Edit Vendor' : 'Create Vendor' }}</h1>
            <a href="{{ route('admin.vendors.index') }}" class="secondary-button">Back</a>
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ $vendor->exists ? route('admin.vendors.update', $vendor->id) : route('admin.vendors.store') }}"
            class="rounded-xl border bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            @csrf
            @if ($vendor->exists)
                @method('PUT')
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                @foreach ([
                    'name' => 'Vendor Name *',
                    'npwp' => 'NPWP',
                    'pic_name' => 'PIC',
                    'phone' => 'Phone',
                    'email' => 'Email',
                    'payment_terms' => 'Payment Terms',
                    'bank_name' => 'Bank',
                    'bank_account_name' => 'Account Name',
                    'bank_account_number' => 'Account Number',
                ] as $field => $label)
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold">{{ $label }}</label>
                        <input
                            name="{{ $field }}"
                            value="{{ old($field, $vendor->{$field}) }}"
                            class="w-full rounded-md border px-3 py-2"
                            {{ $field === 'name' ? 'required' : '' }}
                        >
                    </div>
                @endforeach

                <div>
                    <label class="mb-1.5 block text-sm font-semibold">Status</label>
                    <select name="is_active" class="w-full rounded-md border px-3 py-2">
                        <option value="1" @selected(old('is_active', $vendor->exists ? $vendor->is_active : 1))>Active</option>
                        <option value="0" @selected((string) old('is_active', $vendor->is_active) === '0')>Inactive</option>
                    </select>
                </div>
                {{-- VENDOR NPWP IMAGE CONTACTS V1 --}}
                <div style="grid-column:1/-1;">
                    <div class="rounded-lg border p-4">
                        <label class="mb-1.5 block text-sm font-semibold">Image NPWP</label>

                        <p class="mb-3 text-xs text-gray-500">
                            JPG, JPEG, PNG, atau WebP. Maksimal 5 MB. File disimpan privat.
                        </p>

                        @if ($vendor->exists && ! empty($vendor->npwp_image_path))
                            <div class="mb-3 flex flex-wrap items-center gap-3">
                                <a
                                    href="{{ route('admin.vendors.npwp-image', $vendor->id) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="secondary-button"
                                >
                                    View Image NPWP
                                </a>

                                <label class="flex items-center gap-2 text-xs text-gray-600">
                                    <input type="checkbox" name="remove_npwp_image" value="1">
                                    Hapus image NPWP saat ini
                                </label>
                            </div>
                        @endif

                        <input
                            type="file"
                            name="npwp_image"
                            accept="image/jpeg,image/png,image/webp"
                            class="w-full rounded-md border px-3 py-2"
                        >

                        @error('npwp_image')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <label class="mb-1.5 block text-sm font-semibold">Address</label>
                    <textarea name="address" rows="3" class="w-full rounded-md border px-3 py-2">{{ old('address', $vendor->address) }}</textarea>
                </div>

                <div style="grid-column:1/-1;">
                    <label class="mb-1.5 block text-sm font-semibold">Notes</label>
                    <textarea name="notes" rows="4" class="w-full rounded-md border px-3 py-2">{{ old('notes', $vendor->notes) }}</textarea>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button class="primary-button">Save Vendor</button>
            </div>
        </form>
    </div>
</x-admin::layouts>
