<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            throw new RuntimeException('Tabel vendors tidak ditemukan.');
        }

        if (! Schema::hasColumn('vendors', 'npwp_image_path')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('npwp_image_path', 500)->nullable();
            });
        }

        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'permissions')) {
            return;
        }

        DB::table('roles')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $permissions = is_string($role->permissions ?? null)
                    ? json_decode((string) $role->permissions, true)
                    : ($role->permissions ?? null);

                if (! is_array($permissions) || ! in_array('vendors', $permissions, true)) {
                    continue;
                }

                if (! in_array('contacts.vendors', $permissions, true)) {
                    $permissions[] = 'contacts.vendors';

                    DB::table('roles')->where('id', $role->id)->update([
                        'permissions' => json_encode(array_values($permissions)),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally preserve the NPWP file reference and role aliases.
    }
};