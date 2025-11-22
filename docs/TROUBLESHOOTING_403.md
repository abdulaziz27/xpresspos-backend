# Troubleshooting 403 Forbidden di Owner Panel

## Root Cause Analysis

User yang baru register atau login mendapat **403 Forbidden** saat akses `/owner` panel. Ini terjadi karena beberapa kondisi di `User::canAccessPanel()` yang harus terpenuhi.

---

## Kondisi `canAccessPanel()` untuk Owner Panel

Location: `app/Models/User.php:171-218`

```php
public function canAccessPanel(Panel $panel): bool
{
    // Set team context first
    $storeId = $this->store_id ?? $this->primaryStore()?->id;
    if ($storeId) {
        setPermissionsTeamId($storeId);
    }

    if ($panel->getId() === 'owner') {
        // 1. Admin sistem dan super_admin → ALLOW
        if ($this->hasRole(['admin_sistem', 'super_admin'])) {
            return true;
        }

        // 2. Check email verification ❌ BLOCKER
        if (!$this->email_verified_at) {
            return false;
        }

        // 3. Check if user has store ❌ BLOCKER
        if (!$storeId) {
            return false;
        }

        // 4. Check if store is active ❌ BLOCKER
        $store = Store::find($storeId);
        if ($store && $store->status !== 'active') {
            return false;
        }

        // 5. Check owner role or assignment ❌ BLOCKER
        $hasOwnerRole = $this->hasRole('owner');
        $hasOwnerAssignment = $this->storeAssignments()
            ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
            ->exists();

        return $hasOwnerRole || $hasOwnerAssignment;
    }

    return false;
}
```

---

## Problem: User Baru Tidak Punya `email_verified_at`

### Issue
Saat user register via `/register`, field `email_verified_at` default-nya `NULL`. Akibatnya:
- `canAccessPanel()` return `false` di line 192-194
- User langsung kena **403 Forbidden** begitu akses `/owner`

### Solution (Implemented)

#### 1. Auto-verify saat register
Di `LandingController::register()`:

```php
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    'email_verified_at' => now(), // ✅ Auto-verify
]);
```

#### 2. Auto-verify saat provisioning (fallback)
Di `RegistrationProvisioningService::provisionFor()`:

```php
// 6. Mark email as verified for auto-provisioned users
if (!$user->email_verified_at) {
    $user->email_verified_at = now();
    $user->save();
}
```

**Why both?**
- Register controller: untuk user yang register via form `/register`
- Provisioning service: untuk user yang dibuat via seeder, console, atau flow lain

---

## Checklist untuk User yang Kena 403

Kalau user masih kena 403 setelah register/login, check satu per satu:

### 1. Email Verified? ✅
```sql
SELECT id, email, email_verified_at FROM users WHERE email = 'user@example.com';
```

**Expected**: `email_verified_at` harus **NOT NULL**.

**Fix (manual)**:
```php
php artisan tinker
$user = User::where('email', 'user@example.com')->first();
$user->email_verified_at = now();
$user->save();
```

---

### 2. User Punya Store? ✅
```sql
SELECT id, email, store_id FROM users WHERE email = 'user@example.com';
```

**Expected**: `store_id` harus **NOT NULL**.

**Fix (manual)**:
```php
php artisan tinker
$user = User::where('email', 'user@example.com')->first();

// Check if user has primary store
$store = $user->primaryStore();
if ($store) {
    $user->store_id = $store->id;
    $user->save();
} else {
    echo "User does not have a store. Run provisioning service.";
}
```

---

### 3. User Punya Tenant? ✅
```sql
SELECT * FROM user_tenant_access WHERE user_id = 8;
```

**Expected**: Harus ada record dengan `role = 'owner'`.

**Fix (manual)**:
```php
php artisan tinker
$user = User::find(8);

// Call provisioning service to create tenant + store
app(\App\Services\RegistrationProvisioningService::class)->provisionFor($user);
```

---

### 4. Store Status Active? ✅
```sql
SELECT id, name, status FROM stores WHERE id = '019a9c4a-4315-7399-b620-923502ce79bf';
```

**Expected**: `status = 'active'`.

**Fix (manual)**:
```sql
UPDATE stores SET status = 'active' WHERE id = '019a9c4a-4315-7399-b620-923502ce79bf';
```

---

### 5. User Punya Role 'owner'? ✅
```sql
SELECT * FROM model_has_roles WHERE model_id = 8 AND model_type = 'App\\Models\\User';
```

**Expected**: Harus ada record dengan `role_id` yang sesuai dengan role 'owner'.

**Fix (manual)**:
```php
php artisan tinker
$user = User::find(8);
$user->assignRole('owner');
```

---

## Quick Fix Script (Manual)

Kalau user sudah ada tapi kena 403, bisa pakai script ini untuk manual fix:

```php
php artisan tinker

$user = User::where('email', 'itsdulziz@gmail.com')->first();

// 1. Verify email
if (!$user->email_verified_at) {
    $user->email_verified_at = now();
    $user->save();
    echo "✅ Email verified\n";
}

// 2. Assign owner role
if (!$user->hasRole('owner')) {
    $user->assignRole('owner');
    echo "✅ Owner role assigned\n";
}

// 3. Run auto-provision (create tenant + store if not exist)
app(\App\Services\RegistrationProvisioningService::class)->provisionFor($user);
echo "✅ Auto-provision completed\n";

// 4. Verify canAccessPanel
$panel = \Filament\Facades\Filament::getPanel('owner');
$canAccess = $user->canAccessPanel($panel);
echo $canAccess ? "✅ User can access owner panel\n" : "❌ User still CANNOT access owner panel\n";
```

---

## Automated Tests

Location: `tests/Feature/RegistrationProvisioningTest.php`

All tests untuk auto-provision dan email verification sudah passing:
```
✓ auto provision membuat tenant dan store untuk user baru
✓ auto provision tidak membuat duplikat jika user sudah punya tenant
✓ register endpoint auto provisions user
✓ registered user dapat mengakses owner panel ← Tests email verification
✓ login redirect intended after authentication
```

Run tests:
```bash
php artisan test --filter=RegistrationProvisioningTest
```

---

## Future: Proper Email Verification Flow

Saat ini kita **auto-verify** semua user baru untuk simplicity. Kalau nanti mau implement proper email verification:

### 1. Remove Auto-Verify
Di `LandingController::register()`:
```php
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
    // REMOVE: 'email_verified_at' => now(),
]);
```

### 2. Send Verification Email
```php
use Illuminate\Auth\Events\Registered;

event(new Registered($user)); // Laravel will send verification email
```

### 3. Add Verification Notice di Owner Panel
Di middleware atau Filament panel:
```php
if (!auth()->user()->email_verified_at) {
    Notification::make()
        ->warning()
        ->title('Please verify your email')
        ->body('Check your inbox for verification link.')
        ->persistent()
        ->send();
}
```

### 4. Update `canAccessPanel()`
Bisa tetap require verification, atau bisa bikin lebih lenient:
```php
// Option A: Tetap require verification (strict)
if (!$this->email_verified_at) {
    return false;
}

// Option B: Allow tapi kasih warning (lenient)
// (remove the check, handle di UI layer)
```

---

## Summary

| Problem | Cause | Solution |
|---------|-------|----------|
| 403 Forbidden setelah register | `email_verified_at = NULL` | Auto-verify saat register + provisioning |
| 403 untuk user lama | Missing tenant/store/role | Manual run provisioning service atau fix via tinker |
| Email verification production | Auto-verify tidak ideal | Implement proper Laravel email verification flow |

**Status**: ✅ Fixed for new registrations. Existing users need manual fix.

