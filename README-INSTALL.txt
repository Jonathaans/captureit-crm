INVENTORY PHASE 3A — EQUIPMENT TEMPLATE -> INVENTORY REQUIREMENT

TUJUAN
======
Phase 3A menghubungkan:
Product Equipment Template
    -> Inventory Item master
    -> Delivery Order Item snapshot
    -> Need / Available / Shortage

Phase ini BELUM memilih actual serialized asset seperti CAM-0001 atau CAM-002.
Pemilihan asset aktual dilakukan pada Phase 3B (Allocation / Picking).

STRUKTUR
========
Template:
Camera / Canon EOS 700D / Qty 1
Inventory Item: CAM-700D

Generate Surat Jalan:
Camera / Qty 1
inventory_item_id = CAM-700D

Delivery Order:
Need      1
Available 2
Shortage  0

Untuk serialized:
Available = jumlah inventory_assets dengan status "available".

Untuk quantity:
Available = inventory_items.quantity_on_hand.


FILE YANG DITAMBAHKAN / DIGANTI
================================
1. database/migrations/
   - 2026_08_28_140100_add_inventory_item_id_to_product_equipment_template_items.php
   - 2026_08_28_140110_add_inventory_item_id_to_delivery_order_items.php

2. Full replacement:
   packages/Webkul/Product/src/Models/ProductEquipmentTemplateItem.php
   packages/Webkul/Invoice/src/Models/DeliveryOrderItem.php
   packages/Webkul/Invoice/src/Services/DeliveryOrderService.php

3. New Blade partial:
   packages/Webkul/Admin/src/Resources/views/products/partials/equipment-template.blade.php
   packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-edit.blade.php
   packages/Webkul/Admin/src/Resources/views/delivery-orders/partials/equipment-show.blade.php

4. install-phase3a.ps1
   Script ini otomatis memodifikasi file custom existing:
   - ProductController.php
   - products/edit.blade.php
   - DeliveryOrderController.php
   - delivery-orders/edit.blade.php
   - delivery-orders/show.blade.php

   Script membuat backup terlebih dahulu di:
   storage/app/phase3a-backup/


INSTALL
=======
Jalankan dari root project:

1. Extract ZIP ke:
   C:\Users\Administrator\Documents\laravel-crm-2.2

2. Jalankan installer:
   powershell -ExecutionPolicy Bypass -File .\install-phase3a.ps1

3. Syntax check:
   php -l packages\Webkul\Product\src\Models\ProductEquipmentTemplateItem.php
   php -l packages\Webkul\Invoice\src\Models\DeliveryOrderItem.php
   php -l packages\Webkul\Invoice\src\Services\DeliveryOrderService.php
   php -l packages\Webkul\Admin\src\Http\Controllers\Products\ProductController.php
   php -l packages\Webkul\Admin\src\Http\Controllers\DeliveryOrder\DeliveryOrderController.php

4. Migration:
   php artisan migrate

5. Clear cache:
   php artisan optimize:clear


VERIFY DATABASE
===============
php artisan tinker

Schema::getColumnListing('product_equipment_template_items');
Schema::getColumnListing('delivery_order_items');

Kedua tabel harus mempunyai:
inventory_item_id


TEST 1 — MAP PRODUCT EQUIPMENT TEMPLATE
=======================================
Products -> Edit product yang mempunyai Equipment Template.

Contoh row:
Item: Camera
Description: DSLR Canon 700D
Qty: 1
Unit: unit
Inventory Item:
CAM-700D — Canon EOS 700D (Serialized)

Save Product.

Kemudian cek:
DB::table('product_equipment_template_items')
    ->select('id', 'name', 'inventory_item_id')
    ->get();


TEST 2 — EXISTING SURAT JALAN
=============================
Surat Jalan yang dibuat SEBELUM Phase 3A tetap mempunyai inventory_item_id = null.
Ini benar karena Delivery Order adalah snapshot historis.

Untuk test cepat:
- buka SJ draft lama
- Edit Surat Jalan
- pilih Inventory Item pada equipment
- Save

Show Surat Jalan harus menampilkan:
Inventory Mapping: CAM-700D
Need: 1
Available: 2
Shortage: OK


TEST 3 — NEW SURAT JALAN
========================
Untuk test otomatis Template -> SJ, gunakan Invoice yang BELUM pernah mempunyai Surat Jalan.

Penting:
DeliveryOrderService mencegah double generate.
Jika Invoice sudah mempunyai SJ, tombol Generate akan mengembalikan SJ existing,
bukan membuat ulang snapshot.

Generate SJ baru dari Invoice yang product-nya sudah mempunyai mapping template.

Hasil:
delivery_order_items.inventory_item_id otomatis tercopy dari template.


EXPECTED CURRENT CAMERA TEST
============================
Jika:
CAM-700D mempunyai 2 asset AVAILABLE
Template membutuhkan 1 unit

Maka:
Need      1
Available 2
Shortage  OK

Dashboard:
Available tetap 2
Allocated/Picked tetap 0

Itu benar. Phase 3A hanya requirement/availability.
Phase 3B baru mengalokasikan CAM-0001 atau CAM-002.


ROLLBACK FILE PATCH
===================
Backup file custom ada di:
storage/app/phase3a-backup/

Jangan jalankan migrate:fresh.

NEXT
====
Phase 3B:
- tabel Delivery Order Asset Allocations
- pilih actual serialized asset
- CAM-0001 / CAM-002
- status AVAILABLE -> ALLOCATED
- movement ledger
- prevent double allocation
- quantity reservation
- bukti asset per Surat Jalan
