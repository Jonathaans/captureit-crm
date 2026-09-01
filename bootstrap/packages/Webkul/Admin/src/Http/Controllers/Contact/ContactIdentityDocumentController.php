<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

class ContactIdentityDocumentController extends Controller
{
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
