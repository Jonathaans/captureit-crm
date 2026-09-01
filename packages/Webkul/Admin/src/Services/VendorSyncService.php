<?php

namespace Webkul\Admin\Services;

use Webkul\Admin\Models\Vendor;
use Webkul\Invoice\Models\PurchaseOrder;

class VendorSyncService
{
    public function findOrCreateFromPurchaseOrder(
        PurchaseOrder $purchaseOrder
    ): ?Vendor {
        $name =
            trim(
                (string) $purchaseOrder
                    ->vendor_name
            );

        if ($name === '') {
            return null;
        }

        $normalized =
            $this->normalize(
                $name
            );

        $vendor =
            Vendor::query()
                ->where(
                    'normalized_name',
                    $normalized
                )
                ->first();

        if ($vendor) {
            $this->fillMissingFromPo(
                $vendor,
                $purchaseOrder
            );

            return $vendor;
        }

        return Vendor::query()->create([
            'name' => $name,
            'normalized_name' => $normalized,
            'phone' => $this->nullable(
                $purchaseOrder->vendor_phone
            ),
            'email' => $this->nullable(
                $purchaseOrder->vendor_email
            ),
            'address' => $this->nullable(
                $purchaseOrder->vendor_address
            ),
            'payment_terms' => $this->nullable(
                $purchaseOrder->payment_terms
            ),
            'is_active' => true,
        ]);
    }

    public function normalize(
        string $name
    ): string {
        $value =
            mb_strtolower(
                trim($name)
            );

        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            );

        return (string) $value;
    }

    private function fillMissingFromPo(
        Vendor $vendor,
        PurchaseOrder $purchaseOrder
    ): void {
        $updates = [];

        foreach (
            [
                'phone' => 'vendor_phone',
                'email' => 'vendor_email',
                'address' => 'vendor_address',
                'payment_terms' => 'payment_terms',
            ]
            as $vendorField => $poField
        ) {
            if (
                empty($vendor->{$vendorField})
                && ! empty(
                    $purchaseOrder->{$poField}
                )
            ) {
                $updates[$vendorField] =
                    $purchaseOrder->{$poField};
            }
        }

        if ($updates) {
            $vendor->update($updates);
        }
    }

    private function nullable(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        return $value !== ''
            ? $value
            : null;
    }
}
