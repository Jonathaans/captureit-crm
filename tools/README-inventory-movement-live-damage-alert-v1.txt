INVENTORY MOVEMENT LIVE + DAMAGE ALERT DETAIL V1
================================================

PERBAIKAN INVENTORY MOVEMENT
----------------------------
- DataGrid memakai default urutan occurred_at terbaru (DESC).
- Respons AJAX memakai Cache-Control no-store.
- Halaman melakukan refresh DataGrid otomatis setiap 10 detik saat tab aktif.
- Tombol Refresh Now tersedia untuk mengambil data saat itu juga.
- Filter/search/sort tersimpan ditandai sebagai "Filter/sort aktif".
- Tombol Reset View menghapus state DataGrid Movement yang tersimpan.
- Versi src DataGrid diperbarui agar filter lama seperti "SJ 2609-0001"
  tidak terus menyembunyikan movement terbaru setelah patch dipasang.

Catatan: filter tetap bekerja. Jika filter aktif, hanya movement yang cocok
dengan filter tersebut yang akan tampil. Gunakan Reset View untuk kembali ke
daftar terbaru tanpa filter.

DETAIL ALASAN DAMAGED ASSET
---------------------------
- Alert Damaged Asset mengambil return_notes terakhir dari allocation Return.
- Alert menampilkan "Alasan rusak" dan nomor Surat Jalan sumbernya.
- Detail yang sama tampil di panel Attention Needed.
- Search alert juga dapat mencari teks alasan kerusakan.
- CSV Inventory Alerts menambahkan Damage Reason dan Damage Reference.
- Asset damaged yang bukan berasal dari Return menampilkan fallback bahwa
  alasan kerusakan belum tercatat.

Tidak ada migration baru.

INSTALASI
---------
1. Extract seluruh ZIP ke root project CRM.
2. Jalankan:

   php tools/apply_inventory_movement_live_damage_alert_v1.php

3. Jalankan checker:

   php tools/check_inventory_movement_live_damage_alert_v1.php

4. Test opsional:

   php artisan test --compact tests/Unit/InventoryMovementLiveTest.php tests/Unit/InventoryDamageAlertReasonTest.php

BROWSER TEST
------------
1. Buka Inventory > Movements.
2. Pastikan data terbaru berada di halaman pertama.
3. Buat satu perpindahan dan tunggu maksimal 10 detik.
4. Pastikan movement baru tampil tanpa reload penuh.
5. Isi search; pastikan badge Filter/sort aktif muncul.
6. Klik Reset View; pastikan search/sort kembali kosong.
7. Return satu asset sebagai DAMAGED dan isi alasannya.
8. Buka Inventory Alerts > Asset Issues.
9. Pastikan alert menampilkan alasan rusak dan nomor Surat Jalan.

BACKUP
------
Installer membuat backup source dengan suffix:
.bak-movement-live-damage-alert-v1-YYYYMMDD-HHMMSS

Jika PHP lint gagal, source lama dipulihkan otomatis.
