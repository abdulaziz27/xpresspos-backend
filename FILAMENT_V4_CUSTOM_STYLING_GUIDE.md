# Filament v4 Custom Styling Guide

## 📋 Ringkasan

Dokumen ini menjelaskan best practices untuk membuat custom styling di Filament v4 pages, berdasarkan pengalaman implementasi AI Assistant page yang mengalami masalah styling.

## ❌ Masalah yang Sering Terjadi

### 1. Tailwind CSS Classes Tidak Ter-Compile

**Masalah:**
```blade
<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="lg:col-span-8">...</div>
    <div class="lg:col-span-4">...</div>
</div>
```

**Kenapa tidak bekerja:**
- Filament v4 menggunakan Tailwind dengan konfigurasi khusus
- Custom Tailwind classes mungkin tidak ter-include dalam build
- Responsive classes (`lg:`, `md:`, dll) bisa tidak ter-compile
- Dark mode classes (`dark:border-gray-700`) memerlukan konfigurasi tambahan

### 2. Komponen Filament Internal Tidak Tersedia

**Masalah:**
```blade
<x-filament::field>
    <x-slot name="label">Label</x-slot>
    <input ...>
</x-filament::field>
```

**Error yang muncul:**
```
InvalidArgumentException: Unable to locate a class or view for component [filament::field]
```

**Kenapa tidak bekerja:**
- `<x-filament::field>` bukan public API di Filament v4
- Komponen internal Filament tidak didokumentasikan dan bisa berubah
- Tidak semua komponen tersedia untuk custom pages

### 3. Internal Filament Classes (`fi-*`)

**Masalah:**
```blade
<div class="fi-section-ctn">
<input class="fi-input">
```

**Kenapa tidak bekerja:**
- `fi-*` adalah internal classes yang tidak didokumentasikan
- Bisa berubah tanpa pemberitahuan di update Filament
- Tidak dijamin kompatibel dengan semua konteks
- Bisa konflik dengan styling Filament sendiri

### 4. Styling Tidak Ter-Load

**Masalah:**
- Page ter-render tapi tidak ada styling yang muncul
- Tailwind classes tidak ter-apply
- Layout terlihat "broken" atau tidak ter-styling

**Penyebab:**
- Tailwind classes tidak ter-compile untuk custom page
- Konflik dengan Filament's internal CSS
- CSS tidak ter-load karena scope atau specificity

## ✅ Solusi yang Berhasil

### 1. Gunakan CSS Murni dalam `<style>` Tag

**Pendekatan yang benar:**
```blade
<x-filament-panels::page>
    <style>
        .custom-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .custom-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        .custom-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .custom-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
        }
        .custom-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
    </style>
    
    <div class="custom-container">
        <div class="custom-card">
            <input class="custom-input" type="text">
        </div>
    </div>
</x-filament-panels::page>
```

**Keuntungan:**
- ✅ CSS langsung di-inject ke page, tidak bergantung pada Tailwind compilation
- ✅ Tidak ada konflik dengan Filament's internal classes
- ✅ Lebih predictable dan pasti ter-load
- ✅ Kontrol penuh atas styling

### 2. Gunakan HTML Standar untuk Form Elements

**Pendekatan yang benar:**
```blade
<div>
    <label for="inputId" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #111827;">
        Label Text
    </label>
    <input
        id="inputId"
        type="text"
        wire:model="property"
        class="custom-input"
        placeholder="Placeholder text"
    />
    @error('property')
        <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #dc2626;">{{ $message }}</p>
    @enderror
</div>
```

**Keuntungan:**
- ✅ HTML standar yang selalu bekerja
- ✅ Tidak bergantung pada komponen internal Filament
- ✅ Kompatibel dengan Livewire
- ✅ Mudah di-debug

### 3. Gunakan Custom Classes dengan Prefix

**Pendekatan yang benar:**
```css
/* Gunakan prefix unik untuk menghindari konflik */
.ai-chat-container { }
.ai-message-bubble { }
.ai-input { }
.ai-button { }
```

**Kenapa penting:**
- ✅ Menghindari konflik dengan Filament's internal classes
- ✅ Menghindari konflik dengan Tailwind classes
- ✅ Memudahkan maintenance dan debugging
- ✅ Lebih jelas scope-nya

### 4. Gunakan CSS Grid Murni untuk Layout

**Pendekatan yang benar:**
```css
.custom-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .custom-grid {
        grid-template-columns: 2fr 1fr;
    }
}
```

**Keuntungan:**
- ✅ CSS Grid murni dengan media query standar
- ✅ Lebih fleksibel dan predictable
- ✅ Tidak bergantung pada Tailwind's grid system
- ✅ Responsive dengan mudah

## 📝 Template Standar untuk Custom Page

```blade
<x-filament-panels::page>
    <style>
        /* Container & Layout */
        .page-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .page-grid {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        /* Cards */
        .page-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .page-card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .page-card-body {
            padding: 1.5rem;
        }
        
        /* Form Elements */
        .page-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
        }
        .page-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .page-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #111827;
        }
        
        /* Buttons */
        .page-button {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 500;
            border: none;
        }
        .page-button-primary {
            background: #10b981;
            color: white;
        }
        .page-button-primary:hover {
            background: #059669;
        }
        .page-button-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .page-button-secondary:hover {
            background: #f9fafb;
        }
    </style>
    
    <div class="page-container">
        <div class="page-grid">
            {{-- Main Content --}}
            <div>
                <div class="page-card">
                    <div class="page-card-header">
                        <h3 style="font-weight: 600; font-size: 1rem; margin: 0; color: #111827;">
                            Title
                        </h3>
                    </div>
                    <div class="page-card-body">
                        <form wire:submit.prevent="submitForm">
                            <div>
                                <label for="inputId" class="page-label">
                                    Label
                                </label>
                                <input
                                    id="inputId"
                                    type="text"
                                    wire:model="property"
                                    class="page-input"
                                    placeholder="Placeholder"
                                />
                                @error('property')
                                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #dc2626;">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem;">
                                <button
                                    type="button"
                                    class="page-button page-button-secondary"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="page-button page-button-primary"
                                >
                                    Submit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            {{-- Sidebar --}}
            <div>
                <div class="page-card">
                    <div class="page-card-header">
                        <h3 style="font-weight: 600; font-size: 1rem; margin: 0; color: #111827;">
                            Sidebar
                        </h3>
                    </div>
                    <div class="page-card-body">
                        <!-- Sidebar content -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

## 🎨 Color Palette Standar

Gunakan warna yang konsisten dengan Filament's design system:

```css
/* Primary Colors (Green) */
--primary-50: #ecfdf5;
--primary-500: #10b981;
--primary-600: #059669;
--primary-700: #047857;

/* Gray Scale */
--gray-50: #f9fafb;
--gray-100: #f3f4f6;
--gray-200: #e5e7eb;
--gray-300: #d1d5db;
--gray-400: #9ca3af;
--gray-500: #6b7280;
--gray-600: #4b5563;
--gray-700: #374151;
--gray-900: #111827;

/* Status Colors */
--success: #10b981;
--danger: #dc2626;
--warning: #f59e0b;
--info: #3b82f6;
```

## ⚠️ Hal-Hal yang Harus Dihindari

### ❌ Jangan Gunakan:

1. **Internal Filament Classes (`fi-*`)**
   ```blade
   <!-- JANGAN -->
   <div class="fi-section-ctn">
   <input class="fi-input">
   ```

2. **Komponen Internal Filament yang Tidak Didokumentasikan**
   ```blade
   <!-- JANGAN -->
   <x-filament::field>
   <x-filament::section> <!-- Kecuali yang sudah teruji -->
   ```

3. **Tailwind Classes Kompleks untuk Custom Layout**
   ```blade
   <!-- JANGAN -->
   <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
   ```

4. **Dark Mode Classes Tanpa Konfigurasi**
   ```blade
   <!-- JANGAN (kecuali sudah dikonfigurasi) -->
   <div class="dark:bg-gray-800">
   ```

### ✅ Gunakan:

1. **CSS Murni dalam `<style>` Tag**
2. **HTML Standar untuk Form Elements**
3. **Custom Classes dengan Prefix Unik**
4. **CSS Grid/Flexbox Murni untuk Layout**
5. **Inline Styles untuk Quick Fixes (jika perlu)**

## 🔧 Tips & Tricks

### 1. Responsive Design

```css
/* Mobile First Approach */
.container {
    padding: 1rem;
}
@media (min-width: 640px) {
    .container {
        padding: 1.5rem;
    }
}
@media (min-width: 1024px) {
    .container {
        padding: 2rem;
    }
}
```

### 2. Scrollable Container

```css
.scrollable-container {
    height: 600px;
    overflow-y: auto;
    background: #f9fafb;
    border-radius: 0.5rem;
    padding: 1rem;
    border: 1px solid #e5e7eb;
}
```

### 3. Loading States dengan Livewire

```blade
<button
    type="submit"
    wire:loading.attr="disabled"
    class="page-button page-button-primary"
>
    <span wire:loading.remove wire:target="submitForm">Submit</span>
    <span wire:loading wire:target="submitForm">Loading...</span>
</button>
```

### 4. Error Messages

```blade
@error('property')
    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #dc2626;">
        {{ $message }}
    </p>
@enderror
```

## 📚 Referensi

- **Filament v4 Documentation**: https://filamentphp.com/docs
- **CSS Grid Guide**: https://css-tricks.com/snippets/css/complete-guide-grid/
- **Livewire Documentation**: https://livewire.laravel.com/docs

## 📝 Catatan

- Dokumentasi ini dibuat berdasarkan pengalaman implementasi AI Assistant page
- Best practices ini khusus untuk Filament v4
- Jika Filament update ke versi baru, pastikan untuk mengecek kompatibilitas
- Untuk styling kompleks, pertimbangkan untuk membuat custom CSS file terpisah

---

**Last Updated**: 2025-01-27  
**Filament Version**: v4  
**Laravel Version**: 12.x

