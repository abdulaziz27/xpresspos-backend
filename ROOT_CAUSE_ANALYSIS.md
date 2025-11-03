# Root Cause Analysis: Forbidden Error di Production

## 📋 Ringkasan Masalah

**Gejala:**
- ✅ User `owner@xpresspos.com` ada di database
- ✅ User memiliki `store_id` yang benar: `019a4585-e142-7340-bbaa-b5df58a6ba10`
- ❌ User **TIDAK memiliki role** yang ter-assign (verified via `getRoleNames()` = empty)
- ❌ Login ke `https://dashboard.xpresspos.id/` → **403 Forbidden**

## 🔍 Apa yang Terjadi?

### 1. **User Dibuat Tapi Role Tidak Ter-Assign**

**Evidence dari Tinker:**
```php
$user = User::where('email', 'owner@xpresspos.com')->first();
// ✅ User ditemukan dengan store_id yang benar

$user->getRoleNames();
// ❌ Returns: Illuminate\Support\Collection { all: [] }
// ARTINYA: User TIDAK memiliki role apapun!
```

### 2. **Seeder Sudah Ada Tapi Tidak Berjalan Sempurna**

**Di `FilamentUserSeeder.php`:**
```php
// Line 58-66: Seharusnya assign role
$ownerRole = Role::where('name', 'owner')
    ->where('store_id', $primaryStoreId)
    ->first();

if ($ownerRole && !$owner->hasRole($ownerRole)) {
    setPermissionsTeamId($primaryStoreId);
    $owner->assignRole($ownerRole);
}
```

**Masalah yang mungkin terjadi:**
1. **Role belum ada saat seeder dijalankan**
   - `$ownerRole` = `null` → kondisi `if` tidak terpenuhi
   - Role assignment tidak terjadi
   
2. **Team context tidak ter-set dengan benar**
   - `setPermissionsTeamId()` dipanggil, tapi mungkin ada masalah timing
   - Assignment terjadi tapi tanpa `store_id` di table `model_has_roles`
   
3. **Seeder tidak pernah dijalankan di production**
   - User dibuat manual atau dari seeder lain
   - `FilamentUserSeeder` belum pernah dijalankan

### 3. **Kenapa Di Lokal Bisa?**

Kemungkinan di lokal:
1. **Seeder dijalankan dengan urutan yang benar**
   - `PermissionsAndRolesSeeder` jalan dulu → role terbuat
   - `FilamentUserSeeder` jalan kemudian → role assignment berhasil

2. **Database state berbeda**
   - Mungkin ada data legacy atau testing
   - Role sudah ter-assign dari seeder sebelumnya

3. **Environment yang lebih forgiving**
   - Path-based routing lebih sederhana
   - Middleware stack berbeda

### 4. **Kenapa Di Production Tidak Bisa?**

Di production:
1. **Seeder mungkin tidak pernah dijalankan**
   - User dibuat manual atau dari migration
   - `FilamentUserSeeder` belum pernah dijalankan

2. **Seeder dijalankan tapi gagal silent**
   - Role belum ada saat seeder jalan
   - Kondisi `if ($ownerRole && ...)` gagal
   - Tidak ada error, tapi assignment tidak terjadi

3. **Database state berbeda**
   - Production database mungkin dari import/backup
   - Role assignment tidak ikut ter-copy

## 🔧 Penyebab Utama

**Root Cause:**
User `owner@xpresspos.com` **tidak memiliki role "owner" yang ter-assign** di table `model_has_roles` dengan `store_id` yang benar.

**Kenapa ini menyebabkan 403:**
1. User login berhasil (authentication OK)
2. Middleware `FilamentRoleMiddleware` check: `$user->hasRole('owner')`
3. Karena tidak ada role → `hasRole()` return `false`
4. Middleware juga check `storeAssignments`, tapi mungkin juga tidak ada
5. Akhirnya: `abort(403, 'Unauthorized access to this panel.')`

## ✅ Solusi

### Immediate Fix (Command yang sudah dibuat):
```bash
php artisan user:fix-owner-role owner@xpresspos.com
```

Command ini akan:
1. ✅ Cari user `owner@xpresspos.com`
2. ✅ Pastikan `store_id` ter-set
3. ✅ Cari role "owner" untuk store tersebut
4. ✅ Set team context dengan benar
5. ✅ Assign role dengan team context
6. ✅ Buat store assignment
7. ✅ Verify hasilnya

### Prevention (Untuk Masa Depan):

1. **Pastikan seeder dependencies:**
   ```php
   // PermissionsAndRolesSeeder HARUS jalan dulu
   // FilamentUserSeeder jalan setelahnya
   ```

2. **Add error handling di seeder:**
   ```php
   if (!$ownerRole) {
       $this->command->error('Owner role not found!');
       throw new \Exception('Role missing');
   }
   ```

3. **Add verification setelah seeder:**
   ```php
   // Verify role assignment
   if (!$owner->hasRole('owner')) {
       $this->command->error('Failed to assign role!');
   }
   ```

## 📊 Flow Diagram

```
User Login
    ↓
Authentication (✅ BERHASIL)
    ↓
EnsureFilamentTeamContext
    ↓ Set team context: store_id
FilamentRoleMiddleware
    ↓ Check: hasRole('owner')
    ↓ Query: getRoleNames()
    ↓ Result: [] (EMPTY!)
    ↓ hasRole() = false
    ↓ storeAssignments check
    ↓ Result: false (atau tidak ada)
    ↓
❌ 403 Forbidden
```

## 🎯 Kesimpulan

**Yang terjadi:**
1. User dibuat tapi role tidak ter-assign (seeder gagal atau tidak jalan)
2. Saat login, middleware check role → tidak ada → 403 Forbidden
3. Di lokal mungkin berhasil karena seeder sudah jalan sebelumnya atau state berbeda

**Fix:**
- Jalankan `php artisan user:fix-owner-role owner@xpresspos.com`
- Ini akan assign role dengan team context yang benar
- Setelah itu, login seharusnya berhasil

**Prevention:**
- Pastikan seeder dependencies terpenuhi
- Add verification setelah seeder
- Test seeder di fresh database

