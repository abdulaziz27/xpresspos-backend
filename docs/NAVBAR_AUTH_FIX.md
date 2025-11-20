# Fix: Navbar Menampilkan "Dashboard" untuk Authenticated Users

## Problem
Navbar di homepage masih menampilkan tombol **"Login"** meskipun user sudah login.
Ini ambigu dan membingungkan untuk UX.

## Solution
Update navbar untuk menampilkan:
- **Guest User**: Tombol "Login"
- **Authenticated User**: Tombol "Dashboard" + "Logout"

---

## Changes

### File: `resources/views/components/navbar.blade.php`

#### Desktop Menu (line 17-54)
**Before**:
```html
<a href="{{ route('login') }}" class="...">
    Login
</a>
```

**After**:
```html
@auth
    <!-- Authenticated: Show Dashboard & Logout -->
    <a href="{{ config('app.owner_url', '/owner') }}" class="...">
        Dashboard
    </a>
    <form method="POST" action="{{ route('landing.logout') }}" class="inline">
        @csrf
        <button type="submit" class="...">
            Logout
        </button>
    </form>
@else
    <!-- Guest: Show Login -->
    <a href="{{ route('login') }}" class="...">
        Login
    </a>
@endauth
```

#### Mobile Menu (line 77-114)
Same logic applied to mobile menu for consistency.

---

## User Experience

### Guest User (Not Logged In)
**Desktop**:
```
[Beranda] [Fitur] [Harga] [Login]
```

**Mobile**:
```
≡ Menu
├─ Beranda
├─ Fitur
├─ Harga
└─ [Login]
```

---

### Authenticated User (Logged In)
**Desktop**:
```
[Beranda] [Fitur] [Harga] [Dashboard] [Logout]
```

**Mobile**:
```
≡ Menu
├─ Beranda
├─ Fitur
├─ Harga
├─ [Dashboard]
└─ [Logout]
```

---

## Button Styles

### Dashboard Button
- **Color**: Blue gradient (`from-blue-600 to-blue-700`)
- **Hover**: Darker blue (`from-blue-700 to-blue-800`)
- **Effect**: Scale up + shadow on hover
- **Link**: `config('app.owner_url', '/owner')` → `/owner`

### Logout Button
- **Color**: Gray text (`text-gray-700 dark:text-gray-300`)
- **Hover**: Red accent (`hover:text-red-600`)
- **Effect**: Red background on hover (`hover:bg-red-50`)
- **Action**: POST to `route('landing.logout')`

### Login Button (Guest Only)
- **Color**: Blue gradient (`from-blue-600 to-blue-700`)
- **Hover**: Darker blue
- **Effect**: Scale up + shadow
- **Link**: `route('login')` → `/login`

---

## Testing

### Manual Test Steps

1. **Test as Guest**:
   ```
   1. Logout (or use incognito)
   2. Visit: http://127.0.0.1:8000/
   3. Expected: Navbar shows "Login" button
   4. Click "Login" → Redirects to /login page ✓
   ```

2. **Test as Authenticated User**:
   ```
   1. Login as owner@xpresspos.id
   2. Visit: http://127.0.0.1:8000/
   3. Expected: Navbar shows "Dashboard" + "Logout" buttons
   4. Click "Dashboard" → Redirects to /owner ✓
   5. Click "Logout" → Logs out + redirects to homepage ✓
   ```

3. **Test Mobile Menu**:
   ```
   1. Resize browser to mobile size (< 768px)
   2. Click hamburger menu (≡)
   3. Expected: Same behavior as desktop (Dashboard/Logout or Login)
   4. Click Dashboard → Opens /owner ✓
   5. Click Logout → Logs out ✓
   ```

4. **Test Authenticated → Pricing**:
   ```
   1. Login as authenticated user
   2. Click "Harga" in navbar
   3. Expected: Scrolls to #pricing section ✓
   4. Pricing buttons show dynamic labels (Upgrade/Downgrade/Current) ✓
   ```

---

## Related Features

This fix complements the **Dynamic Pricing Buttons** feature:

1. **Navbar Auth State** (This Fix):
   - Guest → "Login"
   - Authenticated → "Dashboard" + "Logout"

2. **Pricing Buttons** (Previous Fix):
   - Guest → "Beli"
   - Authenticated with Plan → "Upgrade" / "Downgrade" / "Paket Saat Ini ✓"

Both features work together to provide a **consistent, clear UX** for authenticated users.

---

## Configuration

**Dashboard URL** is configurable via `.env`:
```env
OWNER_URL=http://127.0.0.1:8000/owner
```

Fallback default: `/owner`

---

## Benefits

### ✅ **Clear User State**
User immediately knows if they're logged in by looking at navbar

### ✅ **Quick Access to Dashboard**
One-click access to owner panel from any landing page

### ✅ **Easy Logout**
No need to go to dashboard to logout

### ✅ **Consistent UX**
Same behavior on desktop and mobile

### ✅ **No Ambiguity**
Guest = Login button  
Authenticated = Dashboard + Logout

---

## Files Modified

1. `resources/views/components/navbar.blade.php`

**Lines Changed**: ~40 lines (added `@auth` conditionals)

---

## Status

✅ **COMPLETE**  
**Date**: 2025-11-19  
**Impact**: UX improvement for all authenticated users

---

## Next Steps (Optional)

1. **User Profile Dropdown** 📋
   - Show user name/email in navbar
   - Dropdown with: Profile, Settings, Dashboard, Logout

2. **Notification Badge** 🔔
   - Show unread notifications count in navbar
   - Link to notification center

3. **Active Plan Badge** 💎
   - Show current plan name in navbar
   - Quick link to upgrade/manage subscription

4. **Search Bar** 🔍
   - Global search in navbar for dashboard features
   - Quick navigation to stores/products/orders

