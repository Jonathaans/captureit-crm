<?php

namespace Webkul\Admin\Http\Controllers\Vendor;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Models\Vendor;
use Webkul\Admin\Services\VendorSyncService;
use Webkul\Admin\Services\VendorNpwpImageService;

class VendorController extends Controller
{
    public function index(
        Request $request
    ): View {
        $this->authorizeAccess();

        $query =
            Vendor::query()
                ->orderBy('name');

        if ($request->filled('q')) {
            $search =
                '%'
                .trim(
                    (string) $request->input('q')
                )
                .'%';

            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where('name', 'like', $search)
                        ->orWhere('pic_name', 'like', $search)
                        ->orWhere('phone', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('npwp', 'like', $search);
                }
            );
        }

        return view(
            'admin::vendors.index',
            [
                'vendors' =>
                    $query
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function create(): View
    {
        $this->authorizeAccess();

        return view(
            'admin::vendors.form',
            [
                'vendor' => new Vendor(),
            ]
        );
    }

    public function store(
        Request $request,
        VendorSyncService $sync
    ): RedirectResponse {
        $this->authorizeAccess();

        $data =
            $this->validated($request);

        /* VENDOR NPWP NON COLUMN FIELDS V1 */
        unset(
            $data['npwp_image'],
            $data['remove_npwp_image']
        );

        $data['normalized_name'] =
            $sync->normalize(
                $data['name']
            );

        $vendor = Vendor::query()->create($data);

        /* VENDOR NPWP STORE V1 */
        if ($request->hasFile('npwp_image')) {
            VendorNpwpImageService::store(
                $vendor,
                $request->file('npwp_image')
            );
        }

        session()->flash(
            'success',
            'Vendor berhasil dibuat.'
        );

        return redirect()->route(
            'admin.vendors.index'
        );
    }

    public function edit(
        int $id
    ): View {
        $this->authorizeAccess();

        return view(
            'admin::vendors.form',
            [
                'vendor' =>
                    Vendor::query()
                        ->findOrFail($id),
            ]
        );
    }

    public function update(
        Request $request,
        int $id,
        VendorSyncService $sync
    ): RedirectResponse {
        $this->authorizeAccess();

        $vendor =
            Vendor::query()
                ->findOrFail($id);

        $data =
            $this->validated($request);

        /* VENDOR NPWP NON COLUMN FIELDS V1 */
        unset(
            $data['npwp_image'],
            $data['remove_npwp_image']
        );

        $data['normalized_name'] =
            $sync->normalize(
                $data['name']
            );

        $vendor->update($data);

        /* VENDOR NPWP UPDATE V1 */
        if ($request->boolean('remove_npwp_image')) {
            VendorNpwpImageService::delete($vendor);
        }

        if ($request->hasFile('npwp_image')) {
            VendorNpwpImageService::store(
                $vendor,
                $request->file('npwp_image')
            );
        }

        session()->flash(
            'success',
            'Vendor berhasil diperbarui.'
        );

        return redirect()->route(
            'admin.vendors.index'
        );
    }

    /** VENDOR NPWP PRIVATE VIEW V1 */
    public function npwpImage(int $id)
    {
        $this->authorizeAccess();

        $vendor = Vendor::query()->findOrFail($id);

        return VendorNpwpImageService::response($vendor);
    }
    private function validated(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'npwp' => [
                'nullable',
                'string',
                'max:100',
            ],
            'npwp_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_npwp_image' => [
                'nullable',
                'boolean',
            ],            'pic_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:100',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
            ],
            'bank_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'bank_account_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'bank_account_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'payment_terms' => [
                'nullable',
                'string',
                'max:100',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(
            auth()->guard('user')->check(),
            403
        );

        if (
            function_exists('bouncer')
            && ! bouncer()->hasPermission(
                'vendors'
            )
            && ! bouncer()->hasPermission(
                'contacts.vendors'
            )
        ) {
            abort(403);
        }
    }
}
