@extends('layouts.xpresspos')

@section('title', 'XpressPOS - AI Maksimalkan Bisnismu | Point of Sale System Terdepan')
@section('description', 'Kelola toko, restoran, dan bisnis dengan AI. Sistem POS modern dengan inventory management, analytics, dan multi-location support. Mulai gratis hari ini!')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section id="hero" class="relative bg-gradient-to-br from-blue-50 via-white to-blue-50 min-h-screen flex items-center">
        <div class="mx-auto max-w-4xl px-6 text-center">
            <!-- Badge -->
            <div class="mb-8">
                <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 text-sm text-gray-700 border border-gray-200">
                    <span>Manajer Bisnis AI</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
            </div>
            
            <!-- Main Headline -->
            <h1 class="text-5xl sm:text-6xl lg:text-7xl font-bold text-blue-600 mb-8 leading-tight">
                AI Maksimalkan Sahammu
            </h1>
            
            <!-- Subtitle -->
            <p class="text-lg sm:text-xl text-gray-600 max-w-3xl mx-auto mb-12 leading-relaxed">
                Saksikan nilai bisnis recehmu bertambah dipandu oleh AI, tidak perlu sarjana keuangan dan sertifikasi apapun.
            </p>
            
            <!-- CTA Button -->
            <div class="flex justify-center">
                <a href="{{ config('domains.owner', 'http://owner.xpresspos.id') }}" 
                   class="inline-flex items-center px-8 py-4 text-lg font-semibold text-white bg-blue-600 rounded-full hover:bg-blue-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                    Mulai Sekarang
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-base font-semibold leading-7 text-blue-600">Fitur Unggulan</h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">Bagaimana XpressPOS Membantumu?</p>
            </div>
            
            <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                    <!-- Feature 1: Inventory Management -->
                    <div class="flex flex-col">
                        <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900">
                            <svg class="h-5 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            Kelola Inventori Cerdas
                        </dt>
                        <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600">
                            <p class="flex-auto">Pantau stok, prediksi kebutuhan, dan kelola supplier dengan AI. Sistem akan memberikan alert otomatis saat stok menipis.</p>
                        </dd>
                    </div>

                    <!-- Feature 2: AI Analytics -->
                    <div class="flex flex-col">
                        <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900">
                            <svg class="h-5 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            Analisis Bisnis AI
                        </dt>
                        <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600">
                            <p class="flex-auto">Dapatkan insight mendalam tentang performa bisnis dengan bantuan AI. Analisis penjualan, trend, dan prediksi otomatis.</p>
                        </dd>
                    </div>

                    <!-- Feature 3: Smart Reports -->
                    <div class="flex flex-col">
                        <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-gray-900">
                            <svg class="h-5 w-5 flex-none text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            Laporan Otomatis
                        </dt>
                        <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-600">
                            <p class="flex-auto">Laporan keuangan, penjualan, dan operasional otomatis setiap hari. Export ke PDF atau Excel dengan satu klik.</p>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-blue-600 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">Siap Mengembangkan Bisnis Anda?</h2>
                <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-blue-100">
                    Bergabunglah dengan ribuan bisnis yang telah mempercayai XpressPOS
                </p>
                <div class="mt-10 flex items-center justify-center gap-x-6">
                    <a href="{{ config('domains.owner', 'http://owner.xpresspos.id') }}" class="rounded-md bg-white px-6 py-3 text-sm font-semibold text-blue-600 shadow-sm hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Mulai Gratis Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
                <!-- Company Info -->
                <div class="lg:col-span-1">
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-sm">X</span>
                        </div>
                        <span class="text-xl font-bold">XpressPOS</span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed mb-6">
                        Sistem Point of Sale terdepan dengan teknologi AI untuk memaksimalkan potensi bisnis Anda. Kelola toko, restoran, dan bisnis dengan mudah dan efisien.
                    </p>
                    
                    <!-- Social Media -->
                    <div class="flex space-x-4">
                        <a href="https://www.tiktok.com/@xpresspos" target="_blank" rel="noopener noreferrer" 
                           class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-blue-600 transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                            </svg>
                        </a>
                        <a href="https://www.instagram.com/xpresspos.id" target="_blank" rel="noopener noreferrer"
                           class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-pink-600 transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        <a href="https://www.youtube.com/@xpresspos" target="_blank" rel="noopener noreferrer"
                           class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-red-600 transition-colors duration-300">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-3">
                        <li><a href="#hero" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Beranda</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Fitur</a></li>
                        <li><a href="{{ route('landing.pricing') }}" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Harga</a></li>
                        <li><a href="{{ route('landing.contact') }}" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Kontak</a></li>
                        <li><a href="{{ config('domains.owner', 'http://owner.xpresspos.id') }}" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Dashboard</a></li>
                    </ul>
                </div>

                <!-- Products & Services -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Produk & Layanan</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">POS System</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Inventory Management</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">AI Analytics</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">Multi-Location</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">API Integration</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Hubungi Kami</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <a href="mailto:support@xpresspos.id" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                                support@xpresspos.id
                            </a>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <a href="tel:+6281234567890" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                                +62 812-3456-7890
                            </a>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-gray-400 text-sm">
                                24/7 Customer Support
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Section -->
            <div class="border-t border-gray-800 mt-12 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-6">
                        <p class="text-gray-400 text-sm">
                            © {{ date('Y') }} XpressPOS. All rights reserved.
                        </p>
                        <div class="flex space-x-6">
                            <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                                Privacy Policy
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                                Syarat & Ketentuan
                            </a>
                            <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm">
                                Cookie Policy
                            </a>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <p class="text-gray-400 text-sm">
                            Made with ❤️ in Indonesia
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</main>
@endsection
