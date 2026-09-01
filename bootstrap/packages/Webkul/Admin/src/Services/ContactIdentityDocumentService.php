<?php

namespace Webkul\Admin\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContactIdentityDocumentService
{
    public function storePersonKtp(
        int $personId,
        UploadedFile $file
    ): string {
        return $this->storeDocument(
            table: 'persons',
            id: $personId,
            pathColumn: 'ktp_image_path',
            directory: 'contact-identity/persons/'.$personId,
            prefix: 'ktp',
            file: $file
        );
    }

    public function storeOrganizationNpwp(
        int $organizationId,
        UploadedFile $file
    ): string {
        return $this->storeDocument(
            table: 'organizations',
            id: $organizationId,
            pathColumn: 'npwp_image_path',
            directory: 'contact-identity/organizations/'.$organizationId,
            prefix: 'npwp',
            file: $file
        );
    }

    private function storeDocument(
        string $table,
        int $id,
        string $pathColumn,
        string $directory,
        string $prefix,
        UploadedFile $file
    ): string {
        $oldPath = DB::table(
            $table
        )
            ->where(
                'id',
                $id
            )
            ->value(
                $pathColumn
            );

        $extension = strtolower(
            (string) (
                $file->extension()
                ?: 'jpg'
            )
        );

        $fileName =
            $prefix
            .'-'
            .Str::uuid()
            .'.'
            .$extension;

        /*
         * "local" on Laravel 12 is storage/app/private by default.
         *
         * Deliberately NOT using the public disk and no storage:link.
         */
        $newPath = Storage::disk(
            'local'
        )->putFileAs(
            $directory,
            $file,
            $fileName
        );

        if (! $newPath) {
            throw new RuntimeException(
                'Gagal menyimpan dokumen identitas ke private storage.'
            );
        }

        $updated = DB::table(
            $table
        )
            ->where(
                'id',
                $id
            )
            ->update([
                $pathColumn =>
                    $newPath,
            ]);

        /*
         * update() can return 0 if a DB driver reports no changed rows,
         * so verify the entity still exists instead of treating 0 as failure.
         */
        $entityExists = DB::table(
            $table
        )
            ->where(
                'id',
                $id
            )
            ->exists();

        if (! $entityExists) {
            Storage::disk(
                'local'
            )->delete(
                $newPath
            );

            throw new RuntimeException(
                'Contact tidak ditemukan saat menyimpan dokumen identitas.'
            );
        }

        /*
         * Delete the previous private image only AFTER the new path is safely
         * attached to the contact.
         */
        if (
            $oldPath
            && $oldPath !== $newPath
        ) {
            Storage::disk(
                'local'
            )->delete(
                $oldPath
            );
        }

        return $newPath;
    }
}
