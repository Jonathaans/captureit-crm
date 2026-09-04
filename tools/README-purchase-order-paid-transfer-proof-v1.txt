PURCHASE ORDER PAID + COMPRESSED TRANSFER PROOF V1.1
=====================================================

V1.1 memperbaiki preflight penghapusan tombol Mark Completed agar kompatibel
dengan variasi indentasi dan line ending Windows pada file Blade.

PERUBAHAN ALUR
--------------
Draft
- Masih dapat diedit.
- Belum membuat Expense.

Released
- PO sudah dirilis dan menunggu pembayaran.
- Belum membuat Expense.

Paid
- Hanya dapat dipilih dari status Released.
- Wajib upload gambar bukti transfer.
- Gambar divalidasi, dikompres, lalu disimpan secara privat.
- Baru pada tahap ini Expense dibuat.
- Status final; tidak dapat diedit atau dibatalkan.

Cancelled
- Hanya untuk Draft atau Released.
- Tidak membuat Expense.

KOMPRESI GAMBAR
---------------
Kompresi hanya diterapkan pada gambar bukti transfer Purchase Order dan tidak
mengubah upload gambar di modul CRM lain.

- Format input: JPG, JPEG, PNG, WebP
- Maksimum input: 10 MB
- Maksimum hasil: 2000 px pada sisi terpanjang
- Output: JPEG progressive, quality 78
- EXIF/metadata gambar dibuang saat re-encode
- Orientasi foto JPEG dinormalisasi jika PHP EXIF tersedia
- File disimpan di private local storage
- File hanya dibuka melalui route admin yang memiliki permission View PO

INSTALASI
---------
1. Extract seluruh ZIP ke root project Krayin CRM.
2. Pastikan struktur berikut terbentuk:
   tools/apply_purchase_order_paid_transfer_proof_v1.php
   packages/Webkul/Invoice/src/Services/PurchaseOrderPaymentProofService.php
   database/migrations/2026_09_03_160000_add_paid_status_and_payment_proof_to_purchase_orders_table.php
3. Pastikan extension PHP GD aktif.
4. Dari root project jalankan:

   php tools/apply_purchase_order_paid_transfer_proof_v1.php

5. Jalankan checker:

   php tools/check_purchase_order_paid_transfer_proof_v1.php

6. Di Settings > Roles, pastikan role admin/finance yang bertugas membayar PO
   memiliki permission "Mark Purchase Order Paid". Super Admin biasanya sudah
   dapat mengakses seluruh permission.

7. Test browser:
   - Buat/ambil PO Draft
   - Klik Release PO
   - Pastikan belum ada Expense
   - Upload bukti transfer pada halaman detail PO
   - Klik Mark as PAID
   - Pastikan status PAID, gambar dapat dilihat, dan Expense terbentuk

TEST OTOMATIS OPSIONAL
----------------------
php artisan test --compact tests/Unit/PurchaseOrderPaymentProofServiceTest.php

CATATAN DATA LAMA
-----------------
Installer tidak mengubah status atau menghapus Expense lama secara otomatis.
PO Released/Completed lama yang sudah memiliki Expense perlu direkonsiliasi
manual karena sistem tidak dapat mengetahui apakah pembayaran historis benar-
benar sudah terjadi.

BACKUP
------
Sebelum menulis source, installer membuat backup dengan suffix:
.bak-po-paid-v1-YYYYMMDD-HHMMSS

Jika PHP lint gagal, source lama dipulihkan otomatis.
