# Root Cause Analysis: 403 Forbidden Issues

## Problem Statement
Repeatedly experiencing 403 Forbidden errors when logging into Filament Owner panel on production (`dashboard.xpresspos.id`), even after running database seeding. The same login works locally but fails in production.

---

## Root Causes Identified

### 1. **Race Condition in Team Context Setting**

**Issue:**
- `EnsureFilamentTeamContext` middleware runs in the panel middleware stack
- `->auth()` callback (panel access gate) may execute before middleware completes
- If `hasRole('owner')` is called without team context set, Spatie Permission returns `false`

**Why it happens:**
```php
// Middleware stack order in Filament v4:
1. Panel middleware (including EnsureFilamentTeamContext)
2. Auth middleware (Authenticate::class)
3. ->auth() callback <-- This is where role check happens

// Problem: Callback timing is not guaranteed to be AFTER middleware
```

**Evidence:**
- Laravel logs show no `FilamentRoleMiddleware` logs (was removed)
- No `OwnerPanel auth gate` logs (callback wasn't returning true)
- Nginx logs show 403 on `GET / HTTP/2.0` after successful login redirect

### 2. **FilamentUserSeeder Unreliability**

**Issue:**
- `firstOrCreate()` finds existing user but doesn't update `store_id`
- Role assignment uses `hasRole()` check which may fail if:
  - Team context not set correctly
  - User has leftover role assignments from previous seeds
  - Cache not cleared between operations

**Why it causes issues:**
```php
// Old seeder code:
$owner = User::firstOrCreate(['email' => 'owner@xpresspos.com'], [...]);

// If user already exists:
// - store_id might be from old store
// - hasRole() check uses wrong team context
// - assignRole() might be skipped
```

**Evidence:**
- Running `migrate:fresh --seed` multiple times produced inconsistent results
- User sometimes had role, sometimes didn't
- `getRoleNames()` returned empty collection even after seeding

### 3. **Team Context Not Set Before Role Checks**

**Issue:**
- Spatie Permission with teams requires `setPermissionsTeamId()` BEFORE any role operation
- If team context is `null` or wrong, `hasRole('owner')` returns `false` even if the role exists

**Why it's critical:**
```php
// WRONG - Team context not set:
$user->hasRole('owner'); // returns false

// CORRECT - Team context set first:
setPermissionsTeamId($storeId);
$user->hasRole('owner'); // returns true
```

**Evidence:**
- Tinker showed `getRoleNames()` empty when team context not set
- After running `setPermissionsTeamId()`, roles appeared correctly
- Command `user:fix-owner-role` succeeded after explicitly setting team context

### 4. **Middleware vs Panel Gate Execution Order**

**Issue:**
- Cannot rely on middleware order for critical operations in Filament v4
- Panel gates (`->auth()`) need to be self-contained and defensive

**Why it matters:**
- Filament v4 changed architecture from v3
- Middleware may not run in expected order
- Panel gates must explicitly set their own preconditions

---

## Solutions Applied

### 1. **Explicit Team Context in Auth Gate**

**File:** `app/Providers/Filament/OwnerPanelProvider.php`

```php
->auth(function () {
    if (!auth()->check()) {
        return false;
    }

    $user = auth()->user();

    // CRITICAL: Always set team context here
    $storeId = $user->store_id ?? $user->primaryStore()?->id;
    
    if (!$storeId) {
        return false; // No store = no access
    }

    // Set context BEFORE role check
    setPermissionsTeamId($storeId);

    // Now check role (will use correct context)
    $hasOwnerRole = $user->hasRole('owner');
    $hasOwnerAssignment = $user->storeAssignments()
        ->where('assignment_role', \App\Enums\AssignmentRoleEnum::OWNER->value)
        ->exists();

    return $hasOwnerRole || $hasOwnerAssignment;
});
```

**Benefits:**
- Self-contained: doesn't rely on middleware
- Defensive: handles missing store_id
- Dual-check: both role and assignment table
- Logged: debugging info for production issues

### 2. **Force-Clean Role Assignment in Seeder**

**File:** `database/seeders/FilamentUserSeeder.php`

```php
// Always update store_id
if ($owner->store_id !== $primaryStoreId) {
    $owner->update(['store_id' => $primaryStoreId]);
}

// Force clean state
setPermissionsTeamId($primaryStoreId);
$owner->roles()->wherePivot('store_id', $primaryStoreId)->detach();

// Assign fresh
$owner->assignRole($ownerRole);

// Verify
$owner->refresh();
setPermissionsTeamId($primaryStoreId);
if (!$owner->hasRole('owner')) {
    $this->command->error("❌ Failed to assign owner role");
}
```

**Benefits:**
- No leftover roles from previous seeds
- Always fresh assignment
- Verified immediately
- Clear error messages

### 3. **Fallback Store ID Resolution**

Both in auth gate and middleware:
```php
$storeId = $user->store_id;

if (!$storeId) {
    $primaryStore = $user->primaryStore();
    $storeId = $primaryStore?->id;
}
```

**Benefits:**
- Handles users without direct `store_id`
- Uses `StoreUserAssignment` as source of truth
- Consistent across codebase

### 4. **Enhanced Logging**

Added comprehensive logging:
```php
\Log::info('OwnerPanel auth gate', [
    'user_id' => $user->id,
    'user_email' => $user->email,
    'store_id' => $storeId,
    'team_context_set' => getPermissionsTeamId(),
    'has_owner_role' => $hasOwnerRole,
    'has_owner_assignment' => $hasOwnerAssignment,
    'roles' => $user->getRoleNames()->toArray(),
]);
```

**Benefits:**
- Easy debugging in production
- Track team context state
- Verify role checks
- Audit trail

---

## Why This Keeps Happening

### Architectural Reasons:

1. **Spatie Permission with Teams is Complex**
   - Requires team context to be set globally
   - Context is per-request, not per-session
   - Easy to forget in new code

2. **Filament v4 Changed Execution Model**
   - Panel gates run at different time than v3
   - Middleware order not guaranteed
   - Need defensive programming

3. **Database Seeding Not Idempotent**
   - `firstOrCreate` doesn't mean "ensure state"
   - Role assignments can accumulate
   - Need explicit cleanup

### Development Practices:

1. **Not Setting Team Context First**
   - Natural instinct: check `hasRole()` directly
   - Wrong: must set team context first
   - Solution: Create helper method

2. **Relying on Middleware Order**
   - Middleware may not run before gates
   - Gates should be self-sufficient
   - Solution: Set context in gate

3. **Inconsistent Testing**
   - Local uses `owner-panel` path
   - Production uses domain routing
   - Different middleware stacks
   - Solution: Test both paths

---

## Prevention Strategy

### For New Features:

```php
// ✅ ALWAYS do this before role checks:
$storeId = $user->store_id ?? $user->primaryStore()?->id;
setPermissionsTeamId($storeId);

// ✅ THEN check role:
$hasRole = $user->hasRole('owner');
```

### For Seeders:

```php
// ✅ ALWAYS force clean state:
setPermissionsTeamId($storeId);
$user->roles()->wherePivot('store_id', $storeId)->detach();
$user->assignRole($role);

// ✅ VERIFY immediately:
$user->refresh();
setPermissionsTeamId($storeId);
if (!$user->hasRole('expected')) {
    throw new \Exception("Role assignment failed");
}
```

### For Filament Panels:

```php
// ✅ ALWAYS set context in ->auth() gate:
->auth(function () {
    $storeId = auth()->user()->store_id ?? auth()->user()->primaryStore()?->id;
    setPermissionsTeamId($storeId);
    
    // Now safe to check roles
    return auth()->user()->hasRole('expected');
});
```

---

## Testing Checklist

After any changes:

- [ ] Clear all caches: `php artisan optimize:clear`
- [ ] Reset permission cache: `php artisan permission:cache-reset`
- [ ] Verify in Tinker:
  ```php
  $user = User::where('email', 'owner@xpresspos.com')->first();
  setPermissionsTeamId($user->store_id);
  dump($user->getRoleNames());
  dump($user->hasRole('owner'));
  ```
- [ ] Check logs: `tail -f storage/logs/laravel.log | grep "OwnerPanel auth gate"`
- [ ] Test login: `https://dashboard.xpresspos.id/login`
- [ ] Test locally: `http://127.0.0.1:8000/owner-panel/login`

---

## Key Takeaways

1. **Team Context is King**
   - Always set before role operations
   - Cannot rely on middleware
   - Not persistent across requests

2. **Defensive Programming Required**
   - Don't assume middleware ran
   - Don't assume state is clean
   - Always verify critical operations

3. **Seeders Must Be Idempotent**
   - Clean old state explicitly
   - Verify immediately
   - Fail loud and clear

4. **Logging is Essential**
   - Log team context state
   - Log role check results
   - Make debugging possible

5. **Test Both Environments**
   - Local uses different paths
   - Production uses domains
   - Both must work

---

## Related Files

- `app/Providers/Filament/OwnerPanelProvider.php` - Panel configuration
- `app/Http/Middleware/EnsureFilamentTeamContext.php` - Team context middleware
- `database/seeders/FilamentUserSeeder.php` - User creation and role assignment
- `app/Console/Commands/FixOwnerRoleAssignment.php` - Manual fix command

## Commands for Fixing Production

```bash
# 1. Fix role assignment
php artisan user:fix-owner-role owner@xpresspos.com

# 2. Clear caches
php artisan optimize:clear
php artisan permission:cache-reset

# 3. Verify
php artisan tinker --execute="
  \$u = App\Models\User::where('email', 'owner@xpresspos.com')->first();
  setPermissionsTeamId(\$u->store_id);
  dump(['roles' => \$u->getRoleNames(), 'hasOwner' => \$u->hasRole('owner')]);
"
```
