# Cara Menambahkan Bahan dan Stok ke Toko

**Contoh:** Menambahkan Biji Kopi 1KG ke Toko Pusat (dibeli dari Supplier A)

---

## 🎯 Flow yang Benar

### Opsi 1: Via Purchase Order (Recommended - untuk pembelian dari supplier)

**Langkah-langkah:**

1. **Buat Bahan "Biji Kopi"** (jika belum ada)
   - Menu: **Inventori → Bahan**
   - Klik "Tambah"
   - Isi:
     - Nama: `Biji Kopi`
     - SKU: `BIJI-KOPI-001` (atau sesuai)
     - Kategori: `Bahan Baku`
     - UOM: `kg` (pilih dari dropdown)
     - Default Cost: `50000` (misalnya Rp 50.000/kg)
     - Status: `Active`
   - Save

2. **Buat Supplier "Supplier A"** (jika belum ada)
   - Menu: **Inventori → Supplier**
   - Klik "Tambah"
   - Isi:
     - Nama: `Supplier A`
     - Email, Phone, Address (opsional)
     - Status: `Active`
   - Save

3. **Buat Purchase Order**
   - Menu: **Inventori → Purchase Order**
   - Klik "Tambah"
   - Form Header:
     - Store: `Toko Pusat`
     - Supplier: `Supplier A`
     - Status: `Draft`
     - Ordered At: `Tanggal hari ini`
   - Klik "Tambah Item" (di RelationManager)
   - Form Item:
     - Bahan: `Biji Kopi (kg)`
     - Qty Dipesan: `1`
     - Qty Diterima: `0` (akan diisi saat receive)
     - Biaya Satuan: `50000`
     - Total Cost: Auto-calculate
   - Save

4. **Receive Purchase Order**
   - Edit PO yang sudah dibuat
   - Ubah Status: `Draft` → `Approved` → `Received`
   - Edit Item:
     - Qty Diterima: `1` (sesuai yang diterima)
   - Save

5. **Hasil:**
   - ✅ `inventory_movements` terbuat (type: `purchase`)
   - ✅ `stock_levels` ter-update (atau terbuat jika belum ada)
   - ✅ Baris muncul di menu **Stok per Toko** untuk (Toko Pusat, Biji Kopi)

**Catatan:** 
- Auto-generate `inventory_movements` dari PO masih TODO (belum diimplementasi)
- Untuk sekarang, setelah PO received, perlu manual create movement atau pakai Opsi 2

---

### Opsi 2: Via Penyesuaian Stok (Quick - untuk stok awal/inisialisasi)

**Langkah-langkah:**

1. **Buat Bahan "Biji Kopi"** (jika belum ada)
   - Menu: **Inventori → Bahan**
   - Klik "Tambah"
   - Isi seperti di Opsi 1
   - Save

2. **Buat Penyesuaian Stok**
   - Menu: **Inventori → Penyesuaian Stok**
   - Klik "Tambah"
   - Form Header:
     - Store: `Toko Pusat`
     - Alasan: `Inisialisasi` (atau `Selisih Stok`)
     - Tanggal Penyesuaian: `Tanggal hari ini`
     - Catatan: `Stok awal dari pembelian Supplier A`
     - Status: `Draft`
   - Klik "Tambah Item" (di RelationManager)
   - Form Item:
     - Bahan: `Biji Kopi (kg)`
     - Qty Sistem: `0` (read-only, karena belum ada stok)
     - Qty Hasil Hitung: `1` (input manual)
     - Selisih: `1` (auto-calculate: 1 - 0 = 1)
     - Biaya Satuan: Auto dari `InventoryItem.default_cost` atau `StockLevel.average_cost`
     - Total Biaya: Auto-calculate
   - Save

3. **Approve Penyesuaian Stok**
   - Edit Penyesuaian yang sudah dibuat
   - Ubah Status: `Draft` → `Disetujui` (Approved)
   - Save

4. **Hasil (Auto):**
   - ✅ `inventory_movements` terbuat (type: `adjustment_in`, quantity: 1)
   - ✅ `stock_levels` ter-update (atau terbuat jika belum ada)
   - ✅ Baris muncul di menu **Stok per Toko** untuk (Toko Pusat, Biji Kopi)

**Keuntungan Opsi 2:**
- ✅ Langsung auto-generate movements saat approve
- ✅ Cocok untuk stok awal/inisialisasi
- ✅ Tidak perlu buat Supplier dulu (kalau cuma untuk inisialisasi)

---

## 📋 Checklist Step-by-Step (Opsi 2 - Recommended untuk Quick Start)

### Step 1: Buat Bahan
```
Menu: Inventori → Bahan → Tambah
- Nama: Biji Kopi
- SKU: BIJI-KOPI-001
- UOM: kg
- Default Cost: 50000
- Status: Active
→ Save
```

### Step 2: Buat Penyesuaian Stok
```
Menu: Inventori → Penyesuaian Stok → Tambah
Header:
- Store: Toko Pusat
- Alasan: Inisialisasi
- Tanggal: Hari ini
- Catatan: Stok awal 1KG dari Supplier A
- Status: Draft
→ Save

Items (RelationManager):
- Klik "Tambah Item"
- Bahan: Biji Kopi (kg)
- Qty Hasil Hitung: 1
→ Save
```

### Step 3: Approve
```
Edit Penyesuaian Stok
- Status: Draft → Disetujui
→ Save

✅ Auto-generate:
- inventory_movements (adjustment_in, 1 kg)
- stock_levels (Toko Pusat, Biji Kopi, current_stock = 1)
```

### Step 4: Verifikasi
```
Menu: Inventori → Stok per Toko
→ Cari "Biji Kopi" untuk "Toko Pusat"
→ Harusnya muncul dengan current_stock = 1 kg
```

---

## 🔄 Perbandingan Opsi

| Aspek | Opsi 1: Purchase Order | Opsi 2: Penyesuaian Stok |
|-------|------------------------|--------------------------|
| **Use Case** | Pembelian dari supplier (dokumen resmi) | Stok awal / inisialisasi / koreksi |
| **Auto-generate Movements** | ❌ Masih TODO | ✅ Sudah diimplementasi |
| **Butuh Supplier** | ✅ Ya | ❌ Tidak |
| **Tracking PO** | ✅ Ada dokumen PO | ❌ Tidak ada |
| **Recommended untuk** | Pembelian rutin dari supplier | Stok awal / quick setup |

---

## 💡 Rekomendasi

**Untuk contoh user (tambah biji kopi 1KG ke toko pusat):**

**Gunakan Opsi 2 (Penyesuaian Stok)** karena:
- ✅ Langsung bisa dipakai (auto-generate movements sudah jalan)
- ✅ Cocok untuk stok awal
- ✅ Tidak perlu setup Supplier dulu (kalau cuma untuk testing)
- ✅ Lebih cepat dan simple

**Kalau nanti sudah implement PO → Movements:**
- Bisa pakai Opsi 1 untuk pembelian rutin dari supplier
- Lebih proper untuk audit trail pembelian

---

## 🎯 Quick Start (Copy-Paste Flow)

1. **Bahan** → Tambah → "Biji Kopi", UOM: kg, Default Cost: 50000
2. **Penyesuaian Stok** → Tambah → Store: Toko Pusat, Alasan: Inisialisasi
3. **Items** → Tambah Item → Bahan: Biji Kopi, Qty Hitung: 1
4. **Approve** → Status: Draft → Disetujui
5. **Stok per Toko** → Cek → Harusnya muncul 1 kg

✅ **Done!**

