
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.products.edit.title')
    </x-slot>

    {!! view_render_event('admin.products.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.products.update', $product->id)"
        encType="multipart/form-data"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs
                        name="products.edit"
                        :entity="$product"
                     />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.products.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.products.edit.create_button.before', ['product' => $product]) !!}
                        
                        <!-- Edit button for Product -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.products.create.save-btn')
                        </button>

                        {!! view_render_event('admin.products.edit.create_button.after', ['product' => $product]) !!}
                    </div>
                </div>
            </div>

            <div class="flex gap-2.5 max-xl:flex-wrap">
                <!-- Left sub-component -->
                <div class="flex flex-1 flex-col gap-2 max-xl:flex-auto">
                    <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.products.create.general')
                        </p>

                        {!! view_render_event('admin.products.edit.attributes.before', ['product' => $product]) !!}

                        <x-admin::attributes
                            :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                'entity_type' => 'products',
                                ['code', 'NOTIN', ['price', 'quantity']],
                            ])"
                            :entity="$product"
                        />

                        {!! view_render_event('admin.products.edit.attributes.after', ['product' => $product]) !!}
                    </div>
                    {{-- ========================================================= --}}
{{-- EQUIPMENT TEMPLATE --}}
{{-- ========================================================= --}}

@php
    $equipmentTemplate =
        $product->equipmentTemplate;

    $existingEquipmentItems =
        old('equipment_items');

    if ($existingEquipmentItems === null) {
        $existingEquipmentItems =
            $equipmentTemplate
                ?->items
                ?->map(function ($item) {
                    return [
                        'name' =>
                            $item->name,

                        'description' =>
                            $item->description,

                        'quantity' =>
                            $item->quantity,

                        'unit' =>
                            $item->unit,

                        'notes' =>
                            $item->notes,
                    ];
                })
                ->values()
                ->toArray()
            ?? [];
    }

    /*
     * Minimal 10 row.
     * Kalau template punya > 10 barang,
     * semuanya tetap tampil.
     */
    $equipmentRowCount = max(
        10,
        count($existingEquipmentItems)
    );
@endphp


<div
    class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
>
    {{-- HEADER --}}

    <div class="mb-5">
        <p
            class="text-base font-semibold text-gray-800 dark:text-white"
        >
            Equipment Template
        </p>

        <p
            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
        >
            Equipment ini akan otomatis dicopy ke Surat Jalan
            ketika product ini terdapat di Invoice.
        </p>
    </div>


    {{-- TEMPLATE SETTINGS --}}

    <div
        class="mb-5 grid grid-cols-2 gap-4 max-md:grid-cols-1"
    >
        <div>
            <label
                class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
            >
                Template Name
            </label>

            <input
                type="text"
                name="equipment_template_name"
                value="{{ old(
                    'equipment_template_name',
                    $equipmentTemplate?->name
                        ?? $product->name
                            .' Equipment Template'
                ) }}"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none transition-all focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
            >
        </div>


        <div>
            <label
                class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
            >
                Template Status
            </label>

            <div
                class="flex h-[42px] items-center gap-2"
            >
                <input
                    type="hidden"
                    name="equipment_template_active"
                    value="0"
                >

                <input
                    type="checkbox"
                    name="equipment_template_active"
                    value="1"
                    @checked(
                        old(
                            'equipment_template_active',
                            $equipmentTemplate?->is_active
                                ?? true
                        )
                    )
                    class="h-4 w-4"
                >

                <span
                    class="text-sm text-gray-700 dark:text-gray-300"
                >
                    Active
                </span>
            </div>
        </div>
    </div>


    {{-- EQUIPMENT TABLE --}}

    <div class="overflow-x-auto">
        <table
            class="w-full min-w-[900px]"
        >
            <thead>
                <tr
                    class="border-b border-gray-200 dark:border-gray-800"
                >
                    <th
                        class="w-[45px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        #
                    </th>

                    <th
                        class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        Item
                    </th>

                    <th
                        class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        Description
                    </th>

                    <th
                        class="w-[90px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        Qty
                    </th>

                    <th
                        class="w-[100px] px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        Unit
                    </th>

                    <th
                        class="px-2 py-3 text-left text-xs font-semibold text-gray-500"
                    >
                        Notes
                    </th>
                </tr>
            </thead>


            <tbody>
                @for (
                    $index = 0;
                    $index < $equipmentRowCount;
                    $index++
                )
                    @php
                        $item =
                            $existingEquipmentItems[$index]
                            ?? [];
                    @endphp

                    <tr
                        class="border-b border-gray-100 dark:border-gray-800"
                    >
                        {{-- NUMBER --}}

                        <td
                            class="px-2 py-2 text-sm text-gray-500"
                        >
                            {{ $index + 1 }}
                        </td>


                        {{-- ITEM --}}

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][name]"
                                value="{{ $item['name'] ?? '' }}"
                                placeholder="Camera"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>


                        {{-- DESCRIPTION --}}

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][description]"
                                value="{{ $item['description'] ?? '' }}"
                                placeholder="Canon EOS R"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>


                        {{-- QUANTITY --}}

                        <td class="px-2 py-2">
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="equipment_items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? 1 }}"
                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>


                        {{-- UNIT --}}

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][unit]"
                                value="{{ $item['unit'] ?? 'unit' }}"
                                placeholder="unit"
                                class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>


                        {{-- NOTES --}}

                        <td class="px-2 py-2">
                            <input
                                type="text"
                                name="equipment_items[{{ $index }}][notes]"
                                value="{{ $item['notes'] ?? '' }}"
                                placeholder="Catatan"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            >
                        </td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>


    {{-- TEMPLATE NOTES --}}

    <div class="mt-5">
        <label
            class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white"
        >
            Template Notes
        </label>

        <textarea
            name="equipment_template_notes"
            rows="3"
            placeholder="Catatan template equipment..."
            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
        >{{ old(
            'equipment_template_notes',
            $equipmentTemplate?->notes
        ) }}</textarea>
    </div>


    <div
        class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-950"
    >
        Baris dengan nama Item kosong tidak akan disimpan.
        Mengubah template ini tidak mengubah Surat Jalan yang sudah
        pernah dibuat.
    </div>
</div>
                </div>

                <!-- Right sub-component -->
                <div class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full">
                    {!! view_render_event('admin.products.edit.accordion.before', ['product' => $product]) !!}

                    <x-admin::accordion >
                        <x-slot:header>
                            {!! view_render_event('admin.products.edit.accordion.header.before', ['product' => $product]) !!}

                            <div class="flex items-center justify-between">
                                <p class="p-2.5 text-base font-semibold text-gray-800 dark:text-white">
                                    @lang('admin::app.products.create.price')
                                </p>
                            </div>

                            {!! view_render_event('admin.products.edit.accordion.header.after', ['product' => $product]) !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.products.edit.accordion.content.attributes.before', ['product' => $product]) !!}

                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    'entity_type' => 'products',
                                    ['code', 'IN', ['price', 'quantity']],
                                ])"
                                :entity="$product"
                            />

                            {!! view_render_event('admin.products.edit.accordion.content.attributes.after', ['product' => $product]) !!}
                        </x-slot>
                    </x-admin::accordion>

                    {!! view_render_event('admin.products.edit.accordion.after', ['product' => $product]) !!}
                </div>
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.products.edit.form.after') !!}
</x-admin::layouts>
