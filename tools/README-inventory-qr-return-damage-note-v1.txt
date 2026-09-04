INVENTORY QR 20x10 MM + RETURN DAMAGED NOTE V1
==============================================

PERUBAHAN QR LABEL
------------------
- Kertas tetap A4 portrait dengan margin 8 mm.
- Setiap label fisik tepat 20 x 10 mm (2 x 1 cm).
- QR di dalam label tetap persegi 8 x 8 mm agar tidak terdistorsi.
- Grid A4: 9 kolom x 25 baris = 225 label per halaman.
- Asset code dan nama item dicetak kecil di sebelah kanan QR.
- Pada dialog Print gunakan Scale 100% / Actual size. Jangan gunakan Fit to Page.

Catatan: QR 8 mm tergolong sangat kecil. Lakukan satu test print dengan printer,
kertas label, dan scanner sebenarnya sebelum mencetak banyak halaman.

PERUBAHAN RETURN
----------------
- Setelah asset discan, condition tetap default GOOD.
- Ketika operator memilih DAMAGED, field Alasan Barang Rusak muncul.
- Alasan wajib diisi, maksimum 2000 karakter.
- Validasi dilakukan di browser, controller, dan service.
- Alasan disimpan ke return_notes pada allocation.
- Alasan ikut tercatat pada Inventory Movement history.
- Setelah Finalize, alasan rusak terlihat pada halaman Return.
- Return GOOD/FAIR tidak membutuhkan alasan kerusakan.

Tidak ada migration baru karena kolom return_notes sudah tersedia pada project.

INSTALASI
---------
1. Extract seluruh ZIP ke root project CRM.
2. Dari root project jalankan:

   php tools/apply_inventory_qr_return_damage_note_v1.php

3. Setelah muncul [PASS], jalankan:

   php tools/check_inventory_qr_return_damage_note_v1.php

4. Test opsional:

   php artisan test --compact tests/Unit/InventoryQrLabel20x10LayoutTest.php tests/Unit/DeliveryOrderReturnDamageNoteTest.php

BROWSER TEST
------------
1. Buka Inventory Asset QR Labels dan Print Preview.
2. Pilih paper A4 dan Scale 100% / Actual size.
3. Ukur satu label: harus 20 x 10 mm.
4. Scan satu QR hasil print untuk memastikan scanner membaca QR 8 mm.
5. Buka Return Surat Jalan, scan asset, pilih DAMAGED.
6. Pastikan textarea alasan muncul dan Finalize ditolak jika kosong.
7. Isi alasan, Finalize, lalu pastikan alasan terlihat kembali.

BACKUP
------
Installer membuat backup source dengan suffix:
.bak-inventory-qr-return-v1-YYYYMMDD-HHMMSS

Jika PHP lint gagal, source lama dipulihkan otomatis.
