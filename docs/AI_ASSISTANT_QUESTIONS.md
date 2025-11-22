# AI Assistant - Daftar Pertanyaan yang Bisa Dijawab

## 📊 Data yang Tersedia

AI Assistant mengumpulkan data berikut untuk dianalisis:

1. **Sales Summary** (Ringkasan Penjualan)
   - Total penjualan (subtotal)
   - Total order
   - Rata-rata nilai order (AOV)
   - Total diskon
   - Total pajak
   - Total service charge

2. **Sales by Day** (Penjualan Harian)
   - Data harian dengan total penjualan dan jumlah order
   - Maksimal 30 hari terakhir

3. **Top Products** (Produk Terlaris)
   - 10 produk terlaris berdasarkan revenue
   - Data: nama produk, quantity terjual, revenue

4. **COGS Summary** (Ringkasan HPP)
   - Total COGS (Cost of Goods Sold)
   - Gross profit (penjualan - COGS)
   - Gross margin percentage

5. **Low Stock Items** (Item Stok Menipis)
   - 10 item dengan stok di bawah minimum
   - Data: nama item, nama toko, stok saat ini, minimum stok

---

## ✅ Pertanyaan yang BISA Dijawab

### 📈 Penjualan (Sales)

#### Total & Ringkasan
- ✅ "Berapa total penjualan hari ini?"
- ✅ "Berapa total penjualan kemarin?"
- ✅ "Berapa total penjualan 7 hari terakhir?"
- ✅ "Berapa total penjualan bulan ini?"
- ✅ "Berapa total penjualan di toko [nama toko]?"
- ✅ "Berapa total penjualan di toko [nama toko] hari ini?"

#### Order & Transaksi
- ✅ "Berapa banyak order hari ini?"
- ✅ "Berapa rata-rata nilai per order?"
- ✅ "Berapa rata-rata nilai order di toko [nama toko]?"
- ✅ "Berapa banyak transaksi yang selesai hari ini?"

#### Diskon & Pajak
- ✅ "Berapa total diskon yang diberikan?"
- ✅ "Berapa total diskon hari ini?"
- ✅ "Berapa total pajak yang dikenakan?"
- ✅ "Berapa total service charge?"

#### Perbandingan & Tren
- ✅ "Bagaimana tren penjualan 7 hari terakhir?"
- ✅ "Hari apa yang penjualannya paling tinggi?"
- ✅ "Hari apa yang penjualannya paling rendah?"
- ✅ "Apakah penjualan hari ini lebih baik dari kemarin?"
- ✅ "Bandingkan penjualan hari ini dengan kemarin"

### 🍕 Produk (Products)

#### Produk Terlaris
- ✅ "Produk apa yang paling laris?"
- ✅ "10 produk terlaris apa saja?"
- ✅ "Produk apa yang paling banyak terjual?"
- ✅ "Produk apa yang menghasilkan revenue tertinggi?"
- ✅ "Berapa banyak [nama produk] yang terjual?"
- ✅ "Berapa revenue dari [nama produk]?"

#### Analisis Produk
- ✅ "Produk mana yang paling menguntungkan?"
- ✅ "Produk apa yang paling sedikit terjual?"
- ✅ "Produk terlaris di toko [nama toko] apa saja?"

### 💰 COGS & Profitabilitas

#### COGS (Cost of Goods Sold)
- ✅ "Berapa total COGS?"
- ✅ "Berapa total COGS hari ini?"
- ✅ "Berapa total COGS di toko [nama toko]?"

#### Gross Profit & Margin
- ✅ "Berapa gross profit?"
- ✅ "Berapa gross profit hari ini?"
- ✅ "Berapa gross margin percentage?"
- ✅ "Berapa persentase margin keuntungan?"
- ✅ "Apakah bisnis ini profitable?"

### 📦 Inventory & Stok

#### Stok Menipis
- ✅ "Apakah ada stok yang menipis?"
- ✅ "Item apa saja yang stoknya menipis?"
- ✅ "Item mana yang perlu di-restock?"
- ✅ "Berapa banyak item yang stoknya di bawah minimum?"
- ✅ "Item apa yang stoknya menipis di toko [nama toko]?"

#### Detail Stok
- ✅ "Berapa stok saat ini dari [nama item]?"
- ✅ "Item mana yang stoknya paling sedikit?"

### 🔍 Analisis Kombinasi

#### Perbandingan Toko
- ✅ "Bandingkan penjualan antar toko"
- ✅ "Toko mana yang penjualannya paling tinggi?"
- ✅ "Toko mana yang paling profitable?"

#### Analisis Waktu
- ✅ "Kapan penjualan paling tinggi?"
- ✅ "Apakah ada pola penjualan di hari tertentu?"
- ✅ "Bagaimana performa penjualan minggu ini vs minggu lalu?"

---

## ❌ Pertanyaan yang TIDAK BISA Dijawab

### Data yang Tidak Tersedia
- ❌ "Berapa profit bersih?" (tidak ada data biaya operasional)
- ❌ "Berapa biaya operasional?" (tidak dikumpulkan)
- ❌ "Berapa gaji karyawan?" (tidak ada data HR)
- ❌ "Siapa customer yang paling banyak belanja?" (tidak ada data customer detail)
- ❌ "Berapa inventory value total?" (hanya low stock items)
- ❌ "Produk mana yang paling banyak return?" (tidak ada data return)
- ❌ "Berapa waktu rata-rata penyiapan order?" (tidak ada data waktu)
- ❌ "Jam berapa penjualan paling ramai?" (tidak ada data per jam)

### Data Historis Detail
- ❌ "Berapa penjualan bulan lalu?" (hanya 30 hari terakhir)
- ❌ "Produk apa yang laris tahun lalu?" (hanya data periode yang dipilih)
- ❌ "Trend penjualan 6 bulan terakhir?" (hanya 30 hari terakhir)

### Data Real-time Detail
- ❌ "Berapa order yang sedang diproses?" (hanya completed orders)
- ❌ "Berapa stok real-time semua item?" (hanya low stock items)
- ❌ "Berapa pending payment?" (tidak ada data payment status detail)

### Prediksi & Rekomendasi
- ❌ "Berapa prediksi penjualan besok?" (tidak ada fitur prediksi)
- ❌ "Produk apa yang harus saya tambahkan?" (tidak ada rekomendasi)
- ❌ "Kapan waktu terbaik untuk promosi?" (tidak ada analisis prediktif)

---

## 💡 Tips untuk Pertanyaan yang Lebih Baik

### ✅ Gunakan Pertanyaan Spesifik
- ✅ "Berapa total penjualan hari ini?"
- ❌ "Bagaimana penjualan?" (terlalu umum)

### ✅ Sertakan Konteks
- ✅ "Berapa total penjualan di toko Jakarta hari ini?"
- ❌ "Berapa penjualan?" (tidak jelas toko mana)

### ✅ Gunakan Rentang Waktu yang Jelas
- ✅ "Berapa total penjualan 7 hari terakhir?"
- ❌ "Berapa penjualan?" (tidak jelas periodenya)

### ✅ Pertanyaan yang Bisa Dijawab dengan Data
- ✅ "Produk apa yang paling laris?"
- ❌ "Produk apa yang harus saya jual?" (perlu analisis lebih dalam)

---

## 📝 Contoh Pertanyaan untuk Testing

### Level 1: Pertanyaan Sederhana
1. "Berapa total penjualan hari ini?"
2. "Berapa banyak order hari ini?"
3. "Produk apa yang paling laris?"
4. "Apakah ada stok yang menipis?"

### Level 2: Pertanyaan dengan Filter
5. "Berapa total penjualan di toko [nama toko] hari ini?"
6. "Produk terlaris di toko [nama toko] apa saja?"
7. "Berapa gross profit hari ini?"

### Level 3: Pertanyaan Analisis
8. "Bagaimana tren penjualan 7 hari terakhir?"
9. "Hari apa yang penjualannya paling tinggi?"
10. "Bandingkan penjualan hari ini dengan kemarin"

### Level 4: Pertanyaan Kombinasi
11. "Toko mana yang penjualannya paling tinggi?"
12. "Produk apa yang paling menguntungkan?"
13. "Item apa saja yang perlu di-restock?"

---

## 🎯 Batasan AI Assistant

1. **Data Terbatas pada Periode yang Dipilih**
   - Default: hari ini
   - Maksimal: 30 hari untuk data harian
   - Top products: hanya 10 teratas

2. **Hanya Data Completed Orders**
   - Tidak termasuk pending, cancelled, atau refunded orders
   - Hanya order dengan status 'completed'

3. **Tidak Ada Data Real-time Detail**
   - Stok: hanya low stock items (10 teratas)
   - Tidak ada data stok lengkap semua item

4. **Tidak Ada Prediksi atau Rekomendasi**
   - Hanya analisis data historis
   - Tidak ada machine learning untuk prediksi

5. **Bahasa Indonesia**
   - AI diinstruksikan untuk menjawab dalam bahasa Indonesia
   - Pertanyaan bisa dalam bahasa Indonesia atau Inggris

---

## 🔄 Cara Kerja

1. **User memilih filter** (opsional):
   - Toko (semua toko atau toko spesifik)
   - Rentang waktu (hari ini, kemarin, 7 hari, 30 hari, atau custom)

2. **User mengetik pertanyaan**

3. **System mengumpulkan data** berdasarkan filter:
   - Sales summary
   - Sales by day (max 30 hari)
   - Top products (max 10)
   - COGS summary
   - Low stock items (max 10)

4. **Data dikirim ke Gemini AI** dengan prompt:
   - Instruksi untuk menjawab dalam bahasa Indonesia
   - Context JSON dengan semua data
   - Pertanyaan user

5. **AI menganalisis dan menjawab** berdasarkan data yang tersedia

---

**Last Updated**: 2025-01-27  
**AI Model**: Gemini 2.0 Flash  
**Data Source**: Orders, Order Items, COGS History, Stock Levels

