<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            throw new RuntimeException('Tabel purchase_orders tidak ditemukan.');
        }

        $this->setStatusValues([
            'draft',
            'released',
            'paid',
            'completed',
            'cancelled',
        ]);

        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'payment_proof_path')) {
                $table->string('payment_proof_path', 500)
                    ->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'paid_by')) {
                $table->unsignedInteger('paid_by')
                    ->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'paid_by_name')) {
                $table->string('paid_by_name')
                    ->nullable();
            }

            if (! Schema::hasColumn('purchase_orders', 'paid_at')) {
                $table->timestamp('paid_at')
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        DB::table('purchase_orders')
            ->where('status', 'paid')
            ->update(['status' => 'released']);

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $columns = [
                'payment_proof_path',
                'paid_by',
                'paid_by_name',
                'paid_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $this->setStatusValues([
            'draft',
            'released',
            'completed',
            'cancelled',
        ]);
    }

    private function setStatusValues(array $values): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $quoted = collect($values)
            ->map(static fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');

        DB::statement(
            "ALTER TABLE `purchase_orders` MODIFY `status` ENUM({$quoted}) "
            ."NOT NULL DEFAULT 'draft'"
        );
    }
};
