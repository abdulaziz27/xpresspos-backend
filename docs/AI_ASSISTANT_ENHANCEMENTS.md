# AI Assistant - Peningkatan Data & Kecerdasan

## 🎯 Masalah yang Ditemukan

Berdasarkan feedback user, AI terlalu banyak yang tidak tahu dan tidak bisa menjawab pertanyaan seperti:
- "berikan toko di tenant ini" → AI tidak tahu daftar toko
- "berapa jumlah toko dan staff di sini?" → AI tidak tahu data stores dan staff
- "mana yang paling kompeten" → AI tidak punya data kompetensi
- "bulan ini dapat berapa?" → AI tidak bisa menjawab karena data terbatas pada periode yang dipilih

## ✅ Solusi yang Diterapkan

### 1. **Menambahkan Data Stores Info**

**Method baru**: `getStoresInfo()`

```php
protected function getStoresInfo(string $tenantId): array
{
    $stores = Store::where('tenant_id', $tenantId)
        ->where('status', 'active')
        ->orderBy('name')
        ->get(['id', 'name', 'code', 'status', 'created_at']);

    return $stores->map(function ($store) {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'code' => $store->code,
            'status' => $store->status,
        ];
    })->toArray();
}
```

**Data yang dikumpulkan**:
- Daftar semua toko milik tenant
- ID, nama, code, status setiap toko

**Pertanyaan yang sekarang bisa dijawab**:
- ✅ "Berikan toko di tenant ini"
- ✅ "Berapa jumlah toko?"
- ✅ "Apa saja nama toko yang ada?"
- ✅ "Toko mana yang aktif?"

### 2. **Menambahkan Data Staff Info**

**Method baru**: `getStaffInfo()`

```php
protected function getStaffInfo(string $tenantId, ?string $storeId): array
{
    // Get all stores for tenant if storeId is null
    $storeIds = $storeId 
        ? [$storeId] 
        : Store::where('tenant_id', $tenantId)->pluck('id')->toArray();

    // Get staff assignments
    $assignments = StoreUserAssignment::query()
        ->whereIn('store_id', $storeIds)
        ->with(['user', 'store'])
        ->get();

    // Group by store
    $staffByStore = [];
    $totalStaff = 0;
    $uniqueStaffIds = [];

    foreach ($assignments as $assignment) {
        // ... process assignments
    }

    return [
        'total_staff' => $totalStaff,
        'total_stores' => count($storeIds),
        'staff_by_store' => $staffByStore,
    ];
}
```

**Data yang dikumpulkan**:
- Total jumlah staff
- Total jumlah toko
- Staff per toko (nama, role, is_primary)

**Pertanyaan yang sekarang bisa dijawab**:
- ✅ "Berapa jumlah staff?"
- ✅ "Berapa staff di toko [nama toko]?"
- ✅ "Siapa saja staff di toko [nama toko]?"
- ✅ "Staff dengan role apa saja yang ada?"

**Pertanyaan yang TIDAK bisa dijawab** (karena data tidak tersedia):
- ❌ "Mana staff yang paling kompeten?" → Data kompetensi tidak tersedia
- ❌ "Staff mana yang performanya terbaik?" → Data performance tidak tersedia

### 3. **Memperbaiki Prompt untuk Lebih Pintar**

**Perbaikan prompt**:

1. **Menambahkan instruksi untuk menggunakan stores_info**:
   ```
   - Gunakan data stores_info untuk menjawab pertanyaan tentang daftar toko, jumlah toko, dll
   - Jika store_id null, jelaskan bahwa data mencakup semua toko dan sebutkan jumlah toko dari stores_info
   ```

2. **Menambahkan instruksi untuk menggunakan staff_info**:
   ```
   - Gunakan data staff_info untuk menjawab pertanyaan tentang jumlah staff, staff per toko, dll
   - Jika ditanya tentang kompetensi staff, jelaskan bahwa data kompetensi tidak tersedia, hanya data assignment dan role
   ```

3. **Menambahkan instruksi untuk periode yang lebih luas**:
   ```
   - Jika data sales_by_day tidak mencakup periode tersebut, jelaskan bahwa data hanya tersedia untuk periode yang dipilih
   - Sarankan user untuk mengubah filter date range jika ingin melihat periode yang lebih luas
   ```

4. **Instruksi untuk menggunakan semua data secara maksimal**:
   ```
   - Gunakan semua data yang tersedia secara maksimal. Jika ada data yang relevan, gunakan untuk menjawab pertanyaan
   ```

## 📊 Struktur Context Baru

```json
{
  "meta": {
    "tenant_id": "...",
    "store_id": "...",
    "store_name": "...",
    "date_range": {
      "from": "2025-11-22",
      "to": "2025-11-22"
    }
  },
  "sales_summary": {...},
  "sales_by_day": [...],
  "top_products": [...],
  "cogs_summary": {...},
  "low_stock_items": [...],
  "stores_info": [                    // ← BARU
    {
      "id": "...",
      "name": "Toko A",
      "code": "TOKO-A",
      "status": "active"
    },
    ...
  ],
  "staff_info": {                      // ← BARU
    "total_staff": 5,
    "total_stores": 2,
    "staff_by_store": {
      "Toko A": [
        {
          "user_id": "...",
          "user_name": "John Doe",
          "role": "staff",
          "is_primary": true
        },
        ...
      ],
      "Toko B": [...]
    }
  }
}
```

## 🎯 Pertanyaan yang Sekarang Bisa Dijawab

### ✅ **Tentang Toko**
- "Berikan toko di tenant ini"
- "Berapa jumlah toko?"
- "Apa saja nama toko yang ada?"
- "Toko mana yang aktif?"
- "Toko dengan code apa saja?"

### ✅ **Tentang Staff**
- "Berapa jumlah staff?"
- "Berapa staff di toko [nama toko]?"
- "Siapa saja staff di toko [nama toko]?"
- "Staff dengan role apa saja yang ada?"
- "Staff mana yang primary di toko [nama toko]?"

### ✅ **Tentang Periode**
- "Bulan ini dapat berapa?" → AI akan menjelaskan bahwa data hanya untuk periode yang dipilih dan menyarankan untuk mengubah filter
- "Penjualan di tanggal berapa saja yang paling ramai?" → AI akan menggunakan data sales_by_day

## ⚠️ Batasan yang Masih Ada

### ❌ **Data yang Tidak Tersedia**
1. **Kompetensi Staff**
   - Tidak ada data rating/performance staff
   - AI akan menjelaskan bahwa data kompetensi tidak tersedia

2. **Data Historis Panjang**
   - Data sales_by_day hanya 30 hari terakhir
   - Untuk periode lebih luas, user perlu mengubah filter

3. **Data Real-time Detail**
   - Stok: hanya low stock items (10 teratas)
   - Tidak ada data stok lengkap semua item

4. **Data Prediktif**
   - Tidak ada forecasting/prediksi
   - Hanya analisis data historis

## 🚀 Future Enhancements

### 1. **Tambahkan Data Performance Staff**
- Rating/review staff
- Total order yang ditangani
- Revenue yang dihasilkan per staff

### 2. **Tambahkan Data Inventory Lengkap**
- Semua item dengan stok (bukan hanya low stock)
- Nilai inventory total
- Item yang perlu di-restock (lebih dari 10)

### 3. **Tambahkan Data Customer**
- Total customer
- Customer terbaik
- Customer yang paling sering belanja

### 4. **Tambahkan Data Historis Lebih Panjang**
- Data penjualan per bulan (bukan hanya per hari)
- Data tahunan
- Trend jangka panjang

### 5. **Smart Date Range Detection**
- AI bisa mendeteksi pertanyaan tentang periode tertentu
- Otomatis mengubah filter jika perlu
- Contoh: "bulan ini" → auto set filter ke this_month

## 📝 Testing Checklist

Setelah perbaikan, test pertanyaan berikut:

- [ ] "Berikan toko di tenant ini"
- [ ] "Berapa jumlah toko?"
- [ ] "Berapa jumlah staff?"
- [ ] "Berapa staff di toko [nama toko]?"
- [ ] "Siapa saja staff di toko [nama toko]?"
- [ ] "Penjualan di tanggal berapa saja yang paling ramai?"
- [ ] "Bulan ini dapat berapa?" (dengan filter yang sesuai)
- [ ] "Mana staff yang paling kompeten?" (harus menjelaskan bahwa data tidak tersedia)

---

**Last Updated**: 2025-01-27  
**Changes**: Added stores_info and staff_info to context, improved prompt instructions

