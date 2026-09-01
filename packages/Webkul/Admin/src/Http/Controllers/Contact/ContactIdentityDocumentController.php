<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Services\ContactIdentityDocumentService;

class ContactIdentityDocumentController extends Controller
{
    public function editPerson(
        int $id
    ): View {
        $person = DB::table(
            'persons'
        )->find($id);

        abort_unless(
            $person,
            404
        );

        return view(
            'admin::contacts.identity-documents.person',
            compact(
                'person'
            )
        );
    }

    public function updatePerson(
        Request $request,
        int $id,
        ContactIdentityDocumentService $service
    ): RedirectResponse {
        abort_unless(
            DB::table(
                'persons'
            )
                ->where(
                    'id',
                    $id
                )
                ->exists(),
            404
        );

        $validated = $request->validate([
            'ktp_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $service->storePersonKtp(
            $id,
            $request->file(
                'ktp_image'
            )
        );

        session()->flash(
            'success',
            'KTP berhasil disimpan.'
        );

        return redirect()->route(
            'admin.contacts.persons.identity',
            $id
        );
    }

    public function editOrganization(
        int $id
    ): View {
        $organization = DB::table(
            'organizations'
        )->find($id);

        abort_unless(
            $organization,
            404
        );

        return view(
            'admin::contacts.identity-documents.organization',
            compact(
                'organization'
            )
        );
    }

    public function updateOrganization(
        Request $request,
        int $id,
        ContactIdentityDocumentService $service
    ): RedirectResponse {
        abort_unless(
            DB::table(
                'organizations'
            )
                ->where(
                    'id',
                    $id
                )
                ->exists(),
            404
        );

        $validated = $request->validate([
            'npwp_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $service->storeOrganizationNpwp(
            $id,
            $request->file(
                'npwp_image'
            )
        );

        session()->flash(
            'success',
            'NPWP berhasil disimpan.'
        );

        return redirect()->route(
            'admin.contacts.organizations.identity',
            $id
        );
    }

    public function personKtp(
        int $id
    ): StreamedResponse {
        $path = DB::table(
            'persons'
        )
            ->where(
                'id',
                $id
            )
            ->value(
                'ktp_image_path'
            );

        abort_unless(
            $path
            && Storage::disk(
                'local'
            )->exists(
                $path
            ),
            404
        );

        return $this->privateImageResponse(
            path: $path,
            downloadName:
                'KTP-Person-'
                .$id
                .'.'
                .pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
        );
    }

    public function organizationNpwp(
        int $id
    ): StreamedResponse {
        $path = DB::table(
            'organizations'
        )
            ->where(
                'id',
                $id
            )
            ->value(
                'npwp_image_path'
            );

        abort_unless(
            $path
            && Storage::disk(
                'local'
            )->exists(
                $path
            ),
            404
        );

        return $this->privateImageResponse(
            path: $path,
            downloadName:
                'NPWP-Organization-'
                .$id
                .'.'
                .pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
        );
    }

    private function privateImageResponse(
        string $path,
        string $downloadName
    ): StreamedResponse {
        return Storage::disk(
            'local'
        )->response(
            $path,
            $downloadName,
            [
                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ],
            'inline'
        );
    }
}
