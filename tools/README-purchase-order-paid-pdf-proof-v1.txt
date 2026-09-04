PURCHASE ORDER PAID - PRIVATE PDF PROOF + INVOICE VIEW FIX V1
=============================================================

Paket ini merupakan upgrade untuk workflow Purchase Order PAID V1/V1.1 yang
sudah terpasang.

PERUBAHAN
---------
- Menu Pay hanya menerima file PDF.
- PDF wajib diunggah sebelum Purchase Order menjadi PAID.
- Maksimum PDF 10 MB.
- Isi file diperiksa dari MIME dan header PDF, bukan hanya ekstensi nama.
- PDF disimpan di private local storage.
- View Receipt / Bon pada Expense Invoice membuka route privat PO secara benar.
- Receipt PAID lama dinormalisasi dari URL penuh menjadi route relatif agar
  tidak rusak ketika hostname/port aplikasi berubah.
- Bukti gambar yang sudah pernah tersimpan tetap dapat dibuka sebagai data lama.
- Upload file atau gambar pada modul lain tidak diubah.

INSTALASI
---------
1. Extract seluruh ZIP ke root project dan izinkan overwrite file checker.
2. Dari root project jalankan:

   php tools/apply_purchase_order_paid_pdf_proof_v1.php

3. Setelah muncul [PASS], jalankan:

   php tools/check_purchase_order_paid_transfer_proof_v1.php

4. Browser test:
   - Release sebuah PO Draft.
   - Pada menu Pay, pastikan hanya PDF yang dapat dipilih.
   - Mark as PAID.
   - Buka Invoice terkait.
   - Klik View Receipt / Bon.
   - PDF harus terbuka di tab baru.

BACKUP
------
Installer membuat backup source dengan suffix:
.bak-po-paid-pdf-v1-YYYYMMDD-HHMMSS

Jika PHP lint atau normalisasi database gagal, installer memulihkan source lama.
