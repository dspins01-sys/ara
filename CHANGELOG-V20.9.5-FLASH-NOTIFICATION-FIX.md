# ARA CMS V20.9.5 — Flash Notification Fix

## Fix
- Memperbaiki helper `flash()` yang sebelumnya menyimpan pesan lalu langsung mengambil dan menghapusnya dalam request POST yang sama.
- Sekarang `flash('pesan')` hanya menyimpan pesan untuk request berikutnya, sedangkan `flash()` tanpa argumen mengambil pesan sekali lalu menghapusnya.
- Notifikasi SMTP/Save/Test kembali tampil setelah redirect ke `smtp.php`.
- Auto-hide tetap berjalan sekitar 4,5 detik setelah notifikasi benar-benar tampil.
- Tidak mengubah mekanisme SMTP, konfigurasi Gmail, atau alur session lainnya.
