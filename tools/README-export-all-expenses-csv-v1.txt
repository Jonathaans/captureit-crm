EXPORT ALL EXPENSES CSV V1
==========================

TUJUAN
------
Menambahkan tombol "Export All Expenses" pada halaman Invoices.
Export mencakup seluruh expense dari seluruh invoice, tanpa filter status.
Satu baris CSV mewakili satu expense.

KOLOM CSV
---------
1. Invoice Number
2. Project Code
3. Product Event
4. Project Event Date
5. Expense Date
6. Expense Name / Category
7. Amount
8. Note
9. Image / Receipt
10. Created By
11. Created At

MAPPING
-------
- Product Event: daftar nama product dari invoice_items. Jika invoice lama
  tidak mempunyai invoice_items, nilai memakai subject invoice sebagai fallback.
- Expense Name / Category: description dan category dalam satu kolom.
- Image / Receipt: URL lengkap ke receipt pada public storage.
- Amount: decimal mentah agar dapat diolah kembali di spreadsheet.

INSTALL
-------
Letakkan kedua file PHP di folder tools project, lalu jalankan dari root:

php tools/apply_export_all_expenses_csv_v1.php

Installer otomatis membuat backup, menambah controller, route, tombol dan ACL,
menjalankan PHP lint, lalu mencoba menjalankan php artisan optimize:clear.

CHECK
-----
php tools/check_export_all_expenses_csv_v1.php

Jika checker PASS tetapi tombol belum terlihat, aktifkan permission berikut
pada role user:

invoices.expense.export-all

FILE YANG DIUBAH / DITAMBAH
---------------------------
1. packages/Webkul/Admin/src/Http/Controllers/Invoice/ExpenseExportController.php
2. packages/Webkul/Admin/src/Routes/Admin/invoice-routes.php
3. packages/Webkul/Admin/src/Resources/views/invoices/index.blade.php
4. packages/Webkul/Admin/src/Config/acl.php

Tidak ada migration atau perubahan schema database.

BACKUP / ROLLBACK
-----------------
Backup tersimpan di:

storage/app/expense-export-all-v1-backup/<timestamp>/

Untuk rollback, salin kembali invoice-routes.php, index.blade.php dan acl.php
dari backup. Jika ExpenseExportController.php belum ada sebelum install, hapus
file tersebut; jika sudah ada, pulihkan dari backup. Lalu jalankan:

php artisan optimize:clear

CATATAN
-------
- CSV memakai UTF-8 BOM agar terbaca benar di Microsoft Excel.
- Teks dilindungi dari spreadsheet formula injection.
- Data dibaca per batch 500 record agar memory tetap stabil.
- Receipt diekspor sebagai URL; file image tidak ditempel ke CSV.
