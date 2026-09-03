FINANCIAL REPORT - EXPORT ALL EXPENSES V4
==========================================

Tujuan
------
Memperbaiki hasil Export All Expenses yang sebelumnya:
- Project Code kosong
- Product kosong
- Event / Project memakai nama yang sama pada semua baris

Mapping V4
----------
Setiap baris CSV tetap mewakili satu expense dan memakai expense.invoice_id
sebagai kunci pemetaan.

- Project Code: Invoice -> Quote -> Purchase Order
- Product: Invoice Items -> Quote Items -> Purchase Order Items
- Event / Project: Invoice Subject -> Quote Subject -> PO Project Name
- Event Date: Invoice Event Date -> Quote Event Date

Cara instalasi
--------------
1. Extract ZIP di root project. Setelah diekstrak, file harus berada di:
   tools/apply_financial_report_export_all_expenses_v4.php
   tools/check_financial_report_export_all_expenses_v4.php

2. Dari root project, jalankan:
   php tools/apply_financial_report_export_all_expenses_v4.php

3. Jalankan checker:
   php tools/check_financial_report_export_all_expenses_v4.php

4. Buka ulang halaman Financial Report dan export CSV BARU.
   File CSV lama tidak akan berubah.

Yang diubah
-----------
Installer hanya mengganti:
app/Http/Controllers/AllExpensesExportController.php

Route, tombol, dan database tidak diubah. Sebelum mengganti controller,
installer membuat backup otomatis di sebelah controller dengan nama:
AllExpensesExportController.php.bak-financial-expenses-export-v4-YYYYMMDD-HHMMSS

Pengaman
--------
- Preflight memeriksa tabel dan kolom yang diperlukan.
- PHP lint dijalankan setelah controller ditulis.
- Bila lint gagal, controller lama dipulihkan otomatis dari backup.
- artisan optimize:clear dijalankan setelah instalasi berhasil.

Catatan
-------
Jika nilai sumber memang kosong pada Invoice, Quote, dan Purchase Order,
kolom terkait di CSV tetap kosong. Checker menampilkan diagnostic count agar
data sumber yang belum lengkap dapat dibedakan dari kesalahan mapping.
