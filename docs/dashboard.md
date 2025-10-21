# Ringkasan Dashboard Filament

Dokumen ini merangkum halaman dashboard yang tersedia di aplikasi untuk dua jenis panel: **Admin (admin\_sistem)** dan **Owner (pemilik toko)**. Setiap bagian menjelaskan ringkas isi dashboard serta menu yang ada di sidebar dan fungsinya.

## Panel Admin (`/admin`)

### Ringkasan Dashboard
- **Account Widget & Filament Info** – Widget bawaan Filament untuk informasi akun yang sedang login dan versi framework.
- **Admin Stats** – Statistik sistem tingkat global: total toko, total pengguna, jumlah subscription aktif, dan estimasi pendapatan bulanan.
- **System Health** – Tabel overview status tiap toko: status subscription, paket yang digunakan, tanggal kedaluwarsa, jumlah user, dan status aktif toko.

### Menu Sidebar
- **Stores** – Manajemen master data toko: profil, kontak, branding/logo, status aktif, dan JSON settings khusus toko.
- **Users** – Pengelolaan pengguna sistem: data dasar akun, password, penugasan toko, hak akses (role), status verifikasi email, serta statistik order.
- **Subscriptions** – Administrasi langganan: relasi toko ↔ paket, siklus billing, masa berlaku, nominal tagihan, status (aktif/inactive/cancelled/expired/trial), serta metadata tambahan.

## Panel Owner (`/owner`)

### Ringkasan Dashboard
- **Account Widget & Filament Info** – Informasi akun owner yang sedang masuk dan versi Filament.
- **Owner Stats** – Statistik toko harian: jumlah order hari ini, pendapatan yang terselesaikan hari ini, total produk di katalog, dan jumlah member aktif.
- **COGS Summary** – Ringkasan beban pokok penjualan: COGS harian, COGS bulan berjalan beserta perbandingan bulan lalu, dan coverage resep terhadap total produk.
- **Recent Orders** – Daftar 10 order terbaru termasuk status, pelanggan, total nilai, dan waktu transaksi.
- **Low Stock Alert** – Daftar produk yang stoknya sudah menyentuh/minimal level yang ditentukan.
- **Recipe Performance** – Analitik resep: nama produk, biaya resep, cost per unit, jumlah bahan, pemakaian bulanan, dan persentase margin.

### Menu Sidebar
#### Product Management
- **Products** – Kelola katalog produk: gambar, harga, stok, status aktif, favorit, dan pengaturan inventori.
- **Categories** – Manajemen kategori produk: nama, slug, urutan tampil, status aktif, dan deskripsi.
- **Recipes** – Definisi resep produksi: bahan, yield, biaya resep, cost per unit, serta status aktif.

#### Order Management
- **Orders** – Pengelolaan order POS: detail transaksi, status (draft/open/completed/cancelled), keterkaitan meja/member, pembayaran, dan nilai pajak/layanan/diskon.
- **Payments** – Catatan pembayaran order: metode bayar, gateway, status transaksi, invoice terkait, biaya gateway, dan lampiran respon gateway.

#### Customer Management
- **Member Tiers** – Atur level loyalti (mis. Bronze/Silver/Gold) lengkap dengan batas poin, diskon, dan benefit khusus per toko.
- **Members** – Basis data pelanggan/member: informasi kontak, poin loyalti, total belanja, riwayat kunjungan, tier member, dan status aktif.

#### Financial Management
- **Cash Sessions** – Manajemen sesi kasir: waktu buka/tutup, saldo awal/akhir, penjualan/biaya tunai, selisih kas, dan catatan.
- **Expenses** – Pencatatan biaya operasional harian: kategori, nominal, vendor, tanggal, bukti/nota, dan relasi ke sesi kas jika ada.
- **COGS History** – Riwayat Cost of Goods Sold: kaitannya dengan order, kuantitas terjual, biaya per unit, total COGS, metode perhitungan (FIFO/LIFO/Weighted), serta breakdown biaya.

#### Inventory Management
- **Inventory Movements** – Mutasi stok: penjualan, pembelian, transfer, penyesuaian, retur, atau waste lengkap dengan jumlah, biaya, dan referensi sumber.

#### Store Operations
- **Store Settings** – Atur tarif pajak & service charge default, ucapan terima kasih, website toko, serta kredensial Wi-Fi/nota.
- **Tables** – Pengelolaan meja (restoran/kafe): nomor meja, kapasitas, status (available/occupied/reserved/maintenance), lokasi, QR code, order yang sedang berjalan, dan histori okupansi.
