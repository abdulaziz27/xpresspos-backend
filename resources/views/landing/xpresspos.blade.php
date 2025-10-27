@extends('layouts.xpresspos')

@section('title', 'XpressPOS - Maksimalkan Bisnismu | Point of Sale System Terdepan')
@section('description', 'Kelola toko, restoran, dan bisnis dengan mudah. Sistem POS modern dengan inventory management, analytics, dan multi-location support. Mulai gratis hari ini!')

@section('content')
<main class="overflow-visible">
    <!-- Hero Section -->
    <section id="hero" class="relative w-full hero-gradient">
        <!-- Gradient Orbs -->
        <div class="gradient-orb gradient-orb-1" style="pointer-events: none; z-index: 1;"></div>
        <div class="gradient-orb gradient-orb-2" style="pointer-events: none; z-index: 1;"></div>
        
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.02"%3E%3Ccircle cx="30" cy="30" r="1"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-40" style="pointer-events: none; z-index: 1;"></div>
        
        <!-- Content Container -->
        <div class="relative pt-24 md:pt-36">
            <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
                <div class="mx-auto max-w-7xl px-6">
                <div class="text-center sm:mx-auto lg:mr-auto lg:mt-0 animate-fade-in-up">
                    <!-- Badge -->
                    <div class="mb-8 animate-fade-in-up relative z-50" style="animation-delay: 0.2s">
                        <a class="group mx-auto flex w-fit items-center gap-4 rounded-full border bg-blue-100 p-1 pl-4 shadow-md shadow-black/5 transition-all duration-300 hover:bg-blue-200 border-blue-200 relative z-50" href="{{ route('landing.pricing') }}" style="pointer-events: auto;">
                            <span class="text-sm text-blue-800">Smart POS System</span>
                            <span class="block h-4 w-0.5 border-l bg-blue-300"></span>
                            <div class="size-6 overflow-hidden rounded-full bg-white duration-500 group-hover:bg-blue-50">
                                <div class="flex w-12 -translate-x-1/2 duration-500 ease-in-out group-hover:translate-x-0">
                                    <span class="flex size-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right m-auto size-3 text-blue-600" aria-hidden="true">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </span>
                                    <span class="flex size-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right m-auto size-3 text-blue-600" aria-hidden="true">
                                            <path d="M5 12h14"></path>
                                            <path d="m12 5 7 7-7 7"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Main Headline - Fixed z-index and positioning -->
                    <h1 class="relative z-20 text-balance py-5 font-sf text-5xl font-medium leading-none tracking-tighter text-blue-600 sm:text-6xl md:text-7xl lg:text-8xl animate-fade-in-up" style="animation-delay: 0.4s">
                        Maksimalkan Bisnismu
                    </h1>
                    
                    <!-- Subtitle -->
                    <h2 class="mb-6 text-balance font-sf text-2xl font-semibold tracking-tight text-gray-900 md:text-3xl animate-fade-in-up" style="animation-delay: 0.6s">
                        Revolusi Bisnis Anda dengan XpressPOS
                    </h2>
                    
                    <!-- Description -->
                    <p class="mb-12 text-balance font-sf text-lg tracking-tight text-gray-600 md:text-xl max-w-3xl mx-auto animate-fade-in-up" style="animation-delay: 0.8s">
                        Sistem Point of Sale yang powerful, mudah digunakan, dan terjangkau untuk semua jenis bisnis
                    </p>
                    
                    <!-- CTA Button -->
                    <div class="mt-12 flex flex-col items-center justify-center gap-2 md:flex-row animate-fade-in-up relative z-50" style="animation-delay: 1s">
                        <a class="inline-flex items-center justify-center whitespace-nowrap rounded-lg text-base font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white hover:bg-blue-700 h-11 px-8 relative z-50" href="{{ route('landing.pricing') }}" style="pointer-events: auto;">
                            Mulai Sekarang
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Demo Video/Image Placeholder - Simplified animations -->
            <div class="mt-16 px-2 sm:mt-20 md:mt-24 animate-fade-in-up" style="animation-delay: 1.2s">
                <div class="demo-container mx-auto h-auto w-full max-w-6xl rounded-3xl bg-gradient-to-br from-blue-50 via-indigo-50/30 to-indigo-100 p-8 shadow-lg">
                    <div class="flex items-center justify-center h-96 bg-white rounded-2xl border-2 border-dashed border-gray-300 hover:border-blue-300 transition-colors duration-300">
                        <div class="text-center">
                            <div class="relative">
                                <svg class="mx-auto h-16 w-16 text-gray-400 hover:text-blue-500 transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <!-- Play button overlay -->
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center shadow-lg hover:bg-blue-700 transition-colors duration-300 cursor-pointer">
                                        <svg class="w-5 h-5 text-white ml-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M8 5v10l8-5-8-5z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-4 text-sm text-gray-600 font-medium">Demo Video XpressPOS</p>
                            <p class="text-xs text-gray-500">Lihat bagaimana XpressPOS bekerja</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Section Spacer -->
            <div class="h-16 md:h-24"></div>
        </div>
    </section>

    <!-- Partners Section -->
    <section id="client" class="relative w-full partners-gradient py-16 md:py-20">
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
            <div class="group relative m-auto max-w-5xl px-6">
            <div class="absolute inset-0 z-10 flex scale-95 items-center justify-center opacity-0 duration-500 group-hover:scale-100 group-hover:opacity-100">
                <a class="block text-sm duration-150 hover:opacity-75" href="{{ url('/') }}">
                    <span>Cerita menarik tentang kami</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right ml-1 inline-block size-3" aria-hidden="true">
                        <path d="m9 18 6-6-6-6"></path>
                    </svg>
                </a>
            </div>
            <div class="mx-auto mt-12 grid max-w-2xl grid-cols-4 gap-x-6 gap-y-4 transition-all duration-500 group-hover:opacity-50 group-hover:blur-sm md:gap-x-12 md:gap-y-8">
                <!-- Partner Logos -->
                <div class="flex items-center justify-center h-16 bg-gray-100 rounded-lg dark:bg-gray-800">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Partner 1</span>
                </div>
                <div class="flex items-center justify-center h-16 bg-gray-100 rounded-lg dark:bg-gray-800">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Partner 2</span>
                </div>
                <div class="flex items-center justify-center h-16 bg-gray-100 rounded-lg dark:bg-gray-800">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Partner 3</span>
                </div>
                <div class="flex items-center justify-center h-16 bg-gray-100 rounded-lg dark:bg-gray-800">
                    <span class="text-gray-500 dark:text-gray-400 text-sm">Partner 4</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="relative w-full features-gradient py-16 md:py-24 z-10">
        <!-- Gradient Orb -->
        <div class="gradient-orb gradient-orb-3"></div>
        
        <!-- Subtle Decorative Element - Reduced opacity -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/10 via-transparent to-indigo-50/10 pointer-events-none"></div>
        
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
            <div class="relative z-20 mx-auto max-w-5xl space-y-3 text-center">
            <h1 class="text-xl font-semibold capitalize tracking-tight text-blue-600">Fitur Unggulan</h1>
            <h2 class="text-4xl font-bold tracking-tight text-gray-900 md:text-5xl">Bagaimana XpressPOS Membantumu?</h2>
        </div>

        <!-- Feature 1: Inventory Management -->
        <section class="px-4 py-7 sm:px-6 lg:px-8 scroll-animate">
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-4 lg:grid-cols-2 hover:scale-[1.01] transition-transform duration-300">
                    <div class="order-2 space-y-4 lg:order-none lg:order-1">
                        <div class="flex items-center justify-center gap-2 text-sm text-blue-600 md:justify-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package size-5">
                                <path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27,6.96 12,12.01 20.73,6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                            <span>Kelola Inventori</span>
                        </div>
                        <h2 class="text-balance text-3xl font-semibold leading-tight md:text-left lg:text-4xl">Kelola Inventori Cerdas</h2>
                        <p class="text-lg leading-relaxed text-gray-600 md:text-left">Pantau stok, prediksi kebutuhan, dan kelola supplier dengan mudah. Sistem akan memberikan alert otomatis saat stok menipis.</p>
                        <div class="flex justify-center md:justify-start">
                            <a class="inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white hover:bg-blue-700 h-10 px-6" href="{{ route('landing.pricing') }}">
                                Coba Sekarang
                            </a>
                        </div>
                    </div>
                    <div class="relative order-1 lg:order-none lg:order-2">
                        <div class="demo-container mx-auto h-auto w-full max-w-lg rounded-3xl bg-gradient-to-br from-blue-50 via-blue-50/50 to-indigo-100 p-8 shadow-lg">
                            <div class="flex items-center justify-center h-64 bg-white rounded-2xl border-2 border-dashed border-gray-300">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Inventory Demo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature 2: Smart Analytics -->
        <section class="px-4 py-7 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-4 lg:grid-cols-2 lg:grid-flow-col-dense">
                    <div class="order-2 space-y-4 lg:order-none lg:order-2 lg:col-start-2">
                        <div class="flex items-center justify-center gap-2 text-sm text-blue-600 md:justify-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bar-chart-3 size-5">
                                <path d="M3 3v18h18"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg>
                            <span>Analisa Cerdas</span>
                        </div>
                        <h2 class="text-balance text-3xl font-semibold leading-tight md:text-left lg:text-4xl">Analisa Bisnis Mendalam</h2>
                        <p class="text-lg leading-relaxed text-gray-600 md:text-left">Dapatkan insight mendalam tentang performa bisnis dengan laporan analisa yang komprehensif dan mudah dipahami</p>
                        <div class="flex justify-center md:justify-start">
                            <a class="inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white hover:bg-blue-700 h-10 px-6" href="{{ route('landing.pricing') }}">
                                Coba Sekarang
                            </a>
                        </div>
                    </div>
                    <div class="relative order-1 lg:order-none lg:order-1 lg:col-start-1">
                        <div class="demo-container mx-auto h-auto w-full max-w-lg rounded-3xl bg-gradient-to-br from-green-50 via-emerald-50/50 to-emerald-100 p-8 shadow-lg">
                            <div class="flex items-center justify-center h-64 bg-white rounded-2xl border-2 border-dashed border-gray-300">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Analytics Demo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature 3: Smart Reports -->
        <section class="px-4 py-7 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="grid items-center gap-4 lg:grid-cols-2">
                    <div class="order-2 space-y-4 lg:order-none lg:order-1">
                        <div class="flex items-center justify-center gap-2 text-sm text-blue-600 md:justify-start">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text size-5">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                                <polyline points="14,2 14,8 20,8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10,9 9,9 8,9"></polyline>
                            </svg>
                            <span>Laporan Otomatis</span>
                        </div>
                        <h2 class="text-balance text-3xl font-semibold leading-tight md:text-left lg:text-4xl">Laporan Otomatis</h2>
                        <p class="text-lg leading-relaxed text-gray-600 md:text-left">Laporan keuangan, penjualan, dan operasional otomatis setiap hari. Export ke PDF atau Excel dengan satu klik.</p>
                        <div class="flex justify-center md:justify-start">
                            <a class="inline-flex items-center justify-center whitespace-nowrap rounded-lg text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-blue-600 text-white hover:bg-blue-700 h-10 px-6" href="{{ route('landing.pricing') }}">
                                Coba Sekarang
                            </a>
                        </div>
                    </div>
                    <div class="relative order-1 lg:order-none lg:order-2">
                        <div class="demo-container mx-auto h-auto w-full max-w-lg rounded-3xl bg-gradient-to-br from-purple-50 via-violet-50/50 to-violet-100 p-8 shadow-lg">
                            <div class="flex items-center justify-center h-64 bg-white rounded-2xl border-2 border-dashed border-gray-300">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Reports Demo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
            <!-- Section Spacer -->
            <div class="h-16 md:h-24"></div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonial" class="relative w-full testimonial-gradient py-16 md:py-24">
        
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
            <div class="mx-auto max-w-4xl space-y-3 text-center mb-12">
            <h1 class="text-xl font-semibold capitalize tracking-tight text-blue-600">Testimoni</h1>
            <h2 class="text-4xl font-bold tracking-tight text-gray-900 md:text-5xl">Dipercaya oleh 10+ Bisnis</h2>
            <p class="text-lg text-gray-600">Bergabunglah dengan bisnis yang telah merasakan manfaatnya</p>
        </div>

        <!-- Simple Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
            <!-- Testimonial 1 -->
            <div class="bg-gradient-to-br from-white to-blue-50/30 rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 scroll-animate testimonial-card" style="animation-delay: 0.1s">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                        A
                    </div>
                    <div class="text-left">
                        <h4 class="font-semibold text-gray-900">Ahmad</h4>
                        <p class="text-sm text-gray-500">Warung Makan Sederhana</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm leading-relaxed">"Sejak pakai XpressPOS, pencatatan jadi lebih rapi dan tidak ada lagi kesalahan hitung."</p>
            </div>

            <!-- Testimonial 2 -->
            <div class="bg-gradient-to-br from-white to-green-50/30 rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 scroll-animate testimonial-card" style="animation-delay: 0.2s">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-10 w-10 rounded-full bg-green-500 flex items-center justify-center text-white font-semibold">
                        S
                    </div>
                    <div class="text-left">
                        <h4 class="font-semibold text-gray-900">Sari</h4>
                        <p class="text-sm text-gray-500">Toko Kelontong</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm leading-relaxed">"Fitur inventory sangat membantu, sekarang stok selalu terkontrol dengan baik."</p>
            </div>

            <!-- Testimonial 3 -->
            <div class="bg-gradient-to-br from-white to-purple-50/30 rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-all duration-300 scroll-animate testimonial-card" style="animation-delay: 0.3s">
                <div class="flex items-center gap-3 mb-4">
                    <div class="h-10 w-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-semibold">
                        B
                    </div>
                    <div class="text-left">
                        <h4 class="font-semibold text-gray-900">Budi</h4>
                        <p class="text-sm text-gray-500">Cafe Corner</p>
                    </div>
                </div>
                <p class="text-gray-600 text-sm leading-relaxed">"Interface yang mudah dipahami, staff baru bisa langsung pakai tanpa training lama."</p>
            </div>
        </div>

        <!-- Trust Indicators -->
        <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-8 text-center scroll-animate">
            <div class="flex items-center gap-2">
                <div class="flex -space-x-2">
                    <div class="h-8 w-8 rounded-full bg-blue-500 border-2 border-white"></div>
                    <div class="h-8 w-8 rounded-full bg-green-500 border-2 border-white"></div>
                    <div class="h-8 w-8 rounded-full bg-purple-500 border-2 border-white"></div>
                    <div class="h-8 w-8 rounded-full bg-orange-500 border-2 border-white flex items-center justify-center text-white text-xs font-semibold">+7</div>
                </div>
                <span class="text-sm text-gray-600 ml-2"><span data-count="10">0</span>+ bisnis aktif</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex text-yellow-400">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                </div>
                <span class="text-sm text-gray-600">4.8/5 rating</span>
            </div>
        </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="relative w-full pricing-gradient py-16 md:py-24">
        
        <!-- Mesh Gradient Background -->
        <div class="absolute inset-0 mesh-gradient"></div>
        
        <!-- Subtle Background Elements -->
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="%23ddd6fe" fill-opacity="0.03" fill-rule="evenodd"%3E%3Cpath d="M20 20c0-5.5-4.5-10-10-10s-10 4.5-10 10 4.5 10 10 10 10-4.5 10-10zm10 0c0-5.5-4.5-10-10-10s-10 4.5-10 10 4.5 10 10 10 10-4.5 10-10z"/%3E%3C/g%3E%3C/svg%3E')] opacity-20"></div>
        
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
            <div class="mx-auto max-w-5xl space-y-3 text-center mb-12">
            <h1 class="text-xl font-semibold capitalize tracking-tight text-blue-600">Harga</h1>
            <h2 class="text-4xl font-bold tracking-tight text-gray-900 md:text-5xl">Skema Harga Terbaik</h2>
            <p class="text-lg text-gray-600">Dapatkan harga terbaik sesuai kebutuhanmu.</p>
            
            <!-- Toggle Buttons -->
            <div class="flex justify-center mt-8">
                <div class="bg-gray-100 p-1 rounded-lg inline-flex relative">
                    <button id="monthly-btn" class="relative z-10 px-6 py-3 text-sm font-medium text-gray-700 bg-white rounded-md shadow-sm transition-all duration-300 ease-in-out">Bulanan</button>
                    <button id="yearly-btn" class="relative z-10 px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 transition-all duration-300 ease-in-out">
                        Tahunan 
                        <span class="ml-1 px-2 py-0.5 text-xs bg-red-500 text-white rounded-full animate-pulse">Gratis 2 Bulan</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Pricing Comparison Table -->
        <div class="mx-auto max-w-5xl pt-6">
            <div class="overflow-visible rounded-2xl border border-gray-200 bg-white shadow-lg">
                <!-- Header Row -->
                <div class="grid grid-cols-{{ count($plans) + 1 }} bg-gray-50 relative">
                    <div class="p-6 text-left">
                        <h3 class="text-lg font-semibold text-gray-900">Fitur</h3>
                    </div>
                    @foreach($plans as $index => $plan)
                    <div class="p-6 text-center border-l border-gray-200 {{ $index === 1 ? 'bg-blue-50 relative' : '' }} pricing-card">
                        @if($index === 1)
                        <div class="pricing-badge bg-blue-500 text-white px-4 py-1 rounded-full text-sm font-semibold shadow-lg">Populer</div>
                        @endif
                        <div class="mb-2 {{ $index === 1 ? 'pt-4' : '' }}">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h3>
                            <p class="text-sm text-gray-500">{{ Str::limit($plan->description, 50) }}</p>
                        </div>
                        <div class="mt-4">
                            <div class="monthly-price">
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($plan->price, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">/bulan</p>
                            </div>
                            <div class="yearly-price hidden">
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($plan->annual_price, 0, ',', '.') }}</p>
                                <p class="text-xs text-gray-500">/tahun</p>
                            </div>
                            <a href="{{ route('landing.checkout') }}?plan={{ $plan->slug }}&billing=monthly" class="mt-3 w-full {{ $index === 1 ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-900 hover:bg-gray-800' }} text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors inline-block text-center">
                                Beli
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Feature Rows -->
                <div class="divide-y divide-gray-200">
                    @php
                        $allFeatures = collect($plans)->pluck('features')->flatten()->unique()->values();
                    @endphp
                    
                    @foreach($allFeatures as $feature)
                    <div class="grid grid-cols-{{ count($plans) + 1 }} hover:bg-gray-50">
                        <div class="p-4 text-left font-medium text-gray-900">{{ ucwords(str_replace('_', ' ', $feature)) }}</div>
                        @foreach($plans as $index => $plan)
                        <div class="p-4 text-center border-l border-gray-200 {{ $index === 1 ? 'bg-blue-50' : '' }}">
                            @if(in_array($feature, $plan->features))
                                <svg class="w-5 h-5 text-green-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- JavaScript for Toggle -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const monthlyBtn = document.getElementById('monthly-btn');
            const yearlyBtn = document.getElementById('yearly-btn');
            
            monthlyBtn.addEventListener('click', function() {
                switchToMonthly();
            });
            
            yearlyBtn.addEventListener('click', function() {
                switchToYearly();
            });
            
            function switchToMonthly() {
                monthlyBtn.classList.add('bg-white', 'text-gray-700', 'shadow-sm');
                monthlyBtn.classList.remove('text-gray-500');
                yearlyBtn.classList.remove('bg-white', 'text-gray-700', 'shadow-sm');
                yearlyBtn.classList.add('text-gray-500');
                
                // Show monthly prices
                document.querySelectorAll('.monthly-price').forEach(el => {
                    el.classList.remove('hidden');
                });
                document.querySelectorAll('.yearly-price').forEach(el => {
                    el.classList.add('hidden');
                });
            }
            
            function switchToYearly() {
                yearlyBtn.classList.add('bg-white', 'text-gray-700', 'shadow-sm');
                yearlyBtn.classList.remove('text-gray-500');
                monthlyBtn.classList.remove('bg-white', 'text-gray-700', 'shadow-sm');
                monthlyBtn.classList.add('text-gray-500');
                
                // Show yearly prices
                document.querySelectorAll('.yearly-price').forEach(el => {
                    el.classList.remove('hidden');
                });
                document.querySelectorAll('.monthly-price').forEach(el => {
                    el.classList.add('hidden');
                });
            }
        });
        </script>
        </div>
    </section>


    <!-- FAQ Section -->
    <section id="faq" class="relative w-full faq-gradient pb-8 md:pb-20">
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
        <div class="mx-auto max-w-5xl space-y-3 text-center">
            <h1 class="text-xl font-semibold capitalize tracking-tight text-blue-600">FAQ</h1>
            <h2 class="text-4xl font-bold tracking-tight text-gray-900 md:text-5xl">Pertanyaan Umum</h2>
            <h3 class="pt-2 text-sm leading-8 text-gray-600 md:text-base">Jawaban atas pertanyaan umum yang sering ditanyakan.</h3>
        </div>
        <div class="w-full space-y-2 py-8 text-left">
            <!-- FAQ Item 1 -->
            <div class="w-full overflow-hidden rounded-xl border px-3">
                <details class="group">
                    <summary class="flex flex-1 items-center justify-between py-5 text-left text-lg font-medium transition-all hover:no-underline cursor-pointer">
                        Apa itu XpressPOS?
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right size-4 shrink-0 transition-transform duration-200 group-open:rotate-90" aria-hidden="true">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </summary>
                    <div class="pb-5 text-base text-gray-600">
                        XpressPOS adalah sistem Point of Sale (POS) modern yang dirancang untuk membantu bisnis mengelola penjualan, inventori, dan operasional dengan lebih efisien.
                    </div>
                </details>
            </div>

            <!-- FAQ Item 2 -->
            <div class="w-full overflow-hidden rounded-xl border px-3">
                <details class="group">
                    <summary class="flex flex-1 items-center justify-between py-5 text-left text-lg font-medium transition-all hover:no-underline cursor-pointer">
                        Bagaimana cara implementasi XpressPOS?
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right size-4 shrink-0 transition-transform duration-200 group-open:rotate-90" aria-hidden="true">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </summary>
                    <div class="pb-5 text-base text-gray-600">
                        Implementasi sangat mudah! Cukup daftar, ikuti panduan setup awal, dan Anda siap menggunakan XpressPOS dalam hitungan menit. Tim support kami siap membantu jika Anda membutuhkan bantuan.
                    </div>
                </details>
            </div>

            <!-- FAQ Item 3 -->
            <div class="w-full overflow-hidden rounded-xl border px-3">
                <details class="group">
                    <summary class="flex flex-1 items-center justify-between py-5 text-left text-lg font-medium transition-all hover:no-underline cursor-pointer">
                        Apakah XpressPOS cocok untuk bisnis kecil?
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right size-4 shrink-0 transition-transform duration-200 group-open:rotate-90" aria-hidden="true">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </summary>
                    <div class="pb-5 text-base text-gray-600">
                        Sangat cocok! XpressPOS dirancang untuk semua ukuran bisnis, dari warung kecil hingga chain store besar. Paket pricing yang fleksibel sesuai kebutuhan.
                    </div>
                </details>
            </div>

            <!-- FAQ Item 4 -->
            <div class="w-full overflow-hidden rounded-xl border px-3">
                <details class="group">
                    <summary class="flex flex-1 items-center justify-between py-5 text-left text-lg font-medium transition-all hover:no-underline cursor-pointer">
                        Berapa biaya berlangganan?
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right size-4 shrink-0 transition-transform duration-200 group-open:rotate-90" aria-hidden="true">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </summary>
                    <div class="pb-5 text-base text-gray-600">
                        Kami menawarkan paket mulai dari Rp 99.000/bulan untuk paket Basic. Tersedia juga paket Professional dan Enterprise dengan fitur lebih lengkap. Hubungi tim sales untuk penawaran khusus.
                    </div>
                </details>
            </div>
        </div>
        </div>
    </section>

    <!-- Call-to-Action Section -->
    <section class="relative w-full cta-gradient py-16 md:py-24 overflow-hidden">
        <!-- Animated Background Elements -->
        <div class="absolute inset-0">
            <div class="absolute top-0 left-0 w-full h-full bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23ffffff" fill-opacity="0.05"%3E%3Ccircle cx="30" cy="30" r="1.5"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] animate-pulse"></div>
            <div class="absolute top-10 right-10 w-20 h-20 bg-white/10 rounded-full animate-bounce" style="animation-delay: 0.5s;"></div>
            <div class="absolute bottom-10 left-10 w-16 h-16 bg-white/10 rounded-full animate-bounce" style="animation-delay: 1s;"></div>
        </div>
        
        <!-- Content Container -->
        <div class="mx-auto max-w-screen-xl px-6 text-center md:px-8">
            <div class="relative mx-auto max-w-4xl px-6 text-center">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-6 scroll-animate">
                Siap Memulai Revolusi Bisnis Anda?
            </h2>
            <p class="text-xl text-blue-100 mb-8 max-w-2xl mx-auto scroll-animate" style="animation-delay: 0.2s">
                Bergabunglah dengan <span data-count="10">0</span>+ bisnis yang telah merasakan transformasi dengan XpressPOS
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center scroll-animate" style="animation-delay: 0.4s">
                <a href="{{ route('landing.pricing') }}" 
                   class="inline-flex items-center px-8 py-4 text-lg font-semibold text-blue-600 bg-white rounded-full hover:bg-gray-50 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1 btn-magnetic">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Mulai Gratis Sekarang
                </a>
                <a href="{{ route('login') }}" 
                   class="inline-flex items-center px-8 py-4 text-lg font-semibold text-white border-2 border-white rounded-full hover:bg-white hover:text-blue-600 transition-all duration-300 btn-magnetic">
                    Login
                </a>
            </div>
            
            <!-- Trust Badges -->
            <div class="mt-12 flex flex-wrap justify-center items-center gap-8 opacity-80">
                <div class="flex items-center gap-2 text-blue-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm">100% Aman</span>
                </div>
                <div class="flex items-center gap-2 text-blue-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm">Setup 5 Menit</span>
                </div>
                <div class="flex items-center gap-2 text-blue-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm">Support 24/7</span>
                </div>
            </div>
        </div>
        </div>
    </section>
</main>

<!-- Professional CSS Enhancements -->
<style>
    /* Fade-in-up Animation */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in-up {
        animation: fadeInUp 0.8s ease-out forwards;
        opacity: 0;
    }

    /* Scroll-triggered animations */
    .scroll-animate {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.6s ease-out;
    }

    .scroll-animate.animate {
        opacity: 1;
        transform: translateY(0);
    }

    /* Subtle hover effects for cards */
    .testimonial-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .testimonial-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Smooth scrolling */
    html {
        scroll-behavior: smooth;
    }

    /* Fix section overlapping issues */
    section {
        position: relative;
        z-index: 1;
    }

    /* Ensure text elements are always visible */
    h1, h2, h3, h4, h5, h6, p {
        position: relative;
        z-index: 10;
    }

    /* Ensure buttons and links are clickable */
    button, a {
        position: relative;
        z-index: 20;
        pointer-events: auto;
    }

    /* Fix any overlay issues */
    .absolute {
        pointer-events: none;
    }

    .absolute button,
    .absolute a {
        pointer-events: auto;
    }

    /* Ensure gradient orbs don't block clicks */
    .gradient-orb {
        pointer-events: none;
        z-index: 1;
    }

    /* Ensure all interactive elements are above overlays */
    .group,
    .group *,
    button,
    a[href],
    [role="button"],
    input,
    select,
    textarea {
        position: relative;
        z-index: 50 !important;
        pointer-events: auto !important;
    }

    /* Fix background patterns */
    .bg-\[url\(.*\)\] {
        pointer-events: none;
    }

    /* Force clickable elements to be above everything */
    a, button, [role="button"], input, select, textarea {
        position: relative !important;
        z-index: 9999 !important;
        pointer-events: auto !important;
    }

    /* Ensure content containers don't block clicks */
    .relative {
        z-index: auto;
    }

    .relative a,
    .relative button {
        z-index: 9999 !important;
    }

    /* Debug: Add visual indicator for clickable elements */
    a:hover, button:hover {
        cursor: pointer !important;
    }

    /* Pricing section specific fixes */
    #pricing button,
    #pricing a,
    .pricing-card button,
    .pricing-card a {
        position: relative !important;
        z-index: 100 !important;
        pointer-events: auto !important;
    }

    /* Toggle buttons specific fixes */
    #monthly-btn,
    #yearly-btn {
        position: relative !important;
        z-index: 100 !important;
        pointer-events: auto !important;
    }

    /* CTA section fixes */
    .cta-gradient a,
    .cta-gradient button {
        position: relative !important;
        z-index: 100 !important;
        pointer-events: auto !important;
    }

    /* Hero section fixes */
    #hero a,
    #hero button {
        position: relative !important;
        z-index: 100 !important;
        pointer-events: auto !important;
    }

    /* Ensure all background elements don't interfere */
    .hero-gradient::before,
    .hero-gradient::after,
    .features-gradient::before,
    .testimonial-gradient::before,
    .pricing-gradient::before,
    .pricing-gradient::after,
    .partners-gradient::before,
    .faq-gradient::before,
    .cta-gradient::before {
        pointer-events: none !important;
        z-index: 1 !important;
    }

    /* Nuclear option: Force all clickable elements to work */
    * {
        box-sizing: border-box;
    }

    a, button, [role="button"], input, select, textarea, .group {
        position: relative !important;
        z-index: 9999 !important;
        pointer-events: auto !important;
    }

    /* Ensure no pseudo-elements block clicks */
    a::before, a::after,
    button::before, button::after {
        pointer-events: none !important;
    }

    /* Fix specific problematic elements */
    .animate-fade-in-up,
    .scroll-animate,
    .testimonial-card,
    .pricing-card,
    .demo-container {
        pointer-events: auto !important;
    }

    .animate-fade-in-up a,
    .animate-fade-in-up button,
    .scroll-animate a,
    .scroll-animate button {
        pointer-events: auto !important;
        z-index: 9999 !important;
    }

    /* Fix pricing section badge positioning */
    .pricing-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 30;
        white-space: nowrap;
    }

    /* Reduce excessive hover effects on pricing cards */
    .pricing-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .pricing-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Ensure demo sections don't move too much */
    .demo-container {
        transition: transform 0.3s ease;
    }

    .demo-container:hover {
        transform: translateY(-1px);
    }

    /* Additional Animation Classes */
    .fade-in {
        opacity: 0;
        animation: fadeIn 0.6s ease-out forwards;
    }

    @keyframes fadeIn {
        to {
            opacity: 1;
        }
    }

    /* Interactive elements */
    .interactive-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .interactive-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    /* Button hover effects */
    .btn-hover {
        transition: all 0.3s ease;
    }

    .btn-hover:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Gradient Background Styles with Curved Shapes */
    .hero-gradient {
        background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 50%, #f0f4ff 100%);
        position: relative;
        overflow: hidden;
    }

    .hero-gradient::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -20%;
        width: 140%;
        height: 200%;
        background: radial-gradient(ellipse at center, rgba(59, 130, 246, 0.08) 0%, transparent 70%);
        transform: rotate(-15deg);
        z-index: 1;
    }

    .hero-gradient::after {
        content: '';
        position: absolute;
        bottom: -30%;
        right: -10%;
        width: 80%;
        height: 120%;
        background: radial-gradient(ellipse at center, rgba(99, 102, 241, 0.06) 0%, transparent 60%);
        transform: rotate(25deg);
        z-index: 1;
    }

    .features-gradient {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%);
        position: relative;
        overflow: hidden;
    }

    .features-gradient::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 20% 20%, rgba(59, 130, 246, 0.05) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(99, 102, 241, 0.04) 0%, transparent 50%);
        z-index: 1;
    }

    .testimonial-gradient {
        background: linear-gradient(135deg, #fafafa 0%, #ffffff 30%, #f0f9ff 70%, #f8fafc 100%);
        position: relative;
        overflow: hidden;
    }

    .testimonial-gradient::before {
        content: '';
        position: absolute;
        top: -20%;
        left: -30%;
        width: 160%;
        height: 140%;
        background: 
            conic-gradient(from 45deg at 50% 50%, 
                rgba(59, 130, 246, 0.03) 0deg, 
                transparent 90deg, 
                rgba(99, 102, 241, 0.02) 180deg, 
                transparent 270deg, 
                rgba(59, 130, 246, 0.03) 360deg);
        border-radius: 50%;
        z-index: 1;
    }

    .pricing-gradient {
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 40%, #eff6ff 100%);
        position: relative;
        overflow: hidden;
    }

    .pricing-gradient::before {
        content: '';
        position: absolute;
        top: -40%;
        right: -20%;
        width: 120%;
        height: 180%;
        background: 
            radial-gradient(ellipse at center, rgba(59, 130, 246, 0.06) 0%, transparent 70%);
        transform: rotate(-20deg);
        z-index: 1;
    }

    .pricing-gradient::after {
        content: '';
        position: absolute;
        bottom: -40%;
        left: -20%;
        width: 100%;
        height: 160%;
        background: 
            radial-gradient(ellipse at center, rgba(147, 51, 234, 0.04) 0%, transparent 60%);
        transform: rotate(15deg);
        z-index: 1;
    }

    /* Curved Wave Separators */
    .wave-separator {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 60px;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath d='M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z' opacity='.25' fill='%23ffffff'/%3E%3Cpath d='M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z' opacity='.5' fill='%23ffffff'/%3E%3Cpath d='M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z' fill='%23ffffff'/%3E%3C/svg%3E") no-repeat center bottom;
        background-size: cover;
        z-index: 10;
    }

    /* Floating Gradient Orbs */
    .gradient-orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(40px);
        opacity: 0.3;
        animation: float-orb 6s ease-in-out infinite;
    }

    .gradient-orb-1 {
        top: 10%;
        left: 10%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.4) 0%, transparent 70%);
        animation-delay: 0s;
    }

    .gradient-orb-2 {
        top: 60%;
        right: 15%;
        width: 150px;
        height: 150px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%);
        animation-delay: 2s;
    }

    .gradient-orb-3 {
        bottom: 20%;
        left: 20%;
        width: 180px;
        height: 180px;
        background: radial-gradient(circle, rgba(147, 51, 234, 0.25) 0%, transparent 70%);
        animation-delay: 4s;
    }

    @keyframes float-orb {
        0%, 100% {
            transform: translateY(0px) scale(1);
        }
        50% {
            transform: translateY(-20px) scale(1.1);
        }
    }

    /* Mesh Gradient Background */
    .mesh-gradient {
        background: 
            radial-gradient(at 40% 20%, hsla(228,100%,74%,0.05) 0px, transparent 50%),
            radial-gradient(at 80% 0%, hsla(189,100%,56%,0.04) 0px, transparent 50%),
            radial-gradient(at 0% 50%, hsla(355,100%,93%,0.03) 0px, transparent 50%),
            radial-gradient(at 80% 50%, hsla(340,100%,76%,0.04) 0px, transparent 50%),
            radial-gradient(at 0% 100%, hsla(22,100%,77%,0.03) 0px, transparent 50%),
            radial-gradient(at 80% 100%, hsla(242,100%,70%,0.05) 0px, transparent 50%),
            radial-gradient(at 0% 0%, hsla(343,100%,76%,0.04) 0px, transparent 50%);
    }

    /* CTA Section Gradient */
    .cta-gradient {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 25%, #6366f1 50%, #8b5cf6 75%, #a855f7 100%);
        position: relative;
    }

    .cta-gradient::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
        z-index: 1;
    }

    /* Enhanced Gradient Animations */
    @keyframes gradient-shift {
        0%, 100% {
            background-position: 0% 50%;
        }
        50% {
            background-position: 100% 50%;
        }
    }

    .animated-gradient {
        background: linear-gradient(-45deg, #3b82f6, #6366f1, #8b5cf6, #a855f7);
        background-size: 400% 400%;
        animation: gradient-shift 8s ease infinite;
    }

    /* Partners Section Gradient */
    .partners-gradient {
        background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);
        position: relative;
    }

    .partners-gradient::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 30% 30%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
            radial-gradient(circle at 70% 70%, rgba(99, 102, 241, 0.02) 0%, transparent 50%);
        z-index: 1;
    }

    /* FAQ Section Gradient */
    .faq-gradient {
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        position: relative;
    }

    /* Glassmorphism Cards */
    .glass-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    }

    /* Responsive Gradient Adjustments */
    @media (max-width: 768px) {
        .gradient-orb {
            width: 120px;
            height: 120px;
            filter: blur(30px);
        }
        
        .hero-gradient::before,
        .hero-gradient::after {
            opacity: 0.5;
        }
        
        .wave-separator {
            height: 40px;
        }
    }

    /* Reduce motion for accessibility */
    @media (prefers-reduced-motion: reduce) {
        .gradient-orb,
        .animated-gradient {
            animation: none;
        }
        
        .float-orb {
            animation: none;
        }
    }

    /* High contrast mode adjustments */
    @media (prefers-contrast: high) {
        .hero-gradient,
        .features-gradient,
        .testimonial-gradient,
        .pricing-gradient,
        .partners-gradient,
        .faq-gradient {
            background: #ffffff;
        }
        
        .cta-gradient {
            background: #1e40af;
        }
    }

    /* Ensure proper spacing between sections */
    section + section {
        margin-top: 0;
    }

    /* Fix any negative margin issues */
    .negative-margin-fix {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    /* Enhanced button hover effects */
    .btn-enhanced {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .btn-enhanced::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .btn-enhanced:hover::before {
        left: 100%;
    }

    /* Floating animation for badges */
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-3px); }
    }

    .float-animation {
        animation: float 3s ease-in-out infinite;
    }

    /* Gradient text effect */
    .gradient-text {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8, #1e40af);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Gradient text effect */
    .gradient-text {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8, #1e40af);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Advanced Animations */
    @keyframes slideInFromLeft {
        from {
            opacity: 0;
            transform: translateX(-50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideInFromRight {
        from {
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }

    /* Simplified morphing background - removed excessive morphing */
    @keyframes morphing {
        0%, 100% {
            border-radius: 50%;
        }
        50% {
            border-radius: 45%;
        }
    }

    /* Typing effect */
    @keyframes typing {
        from { width: 0 }
        to { width: 100% }
    }

    @keyframes blink {
        50% { border-color: transparent }
    }

    /* Glass morphism effect */
    .glass-morphism {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Simplified glow effect */
    .neon-glow {
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.2);
        transition: box-shadow 0.3s ease;
    }

    .neon-glow:hover {
        box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
    }

    /* Advanced button effects */
    .btn-magnetic {
        transition: all 0.3s cubic-bezier(0.23, 1, 0.320, 1);
        position: relative;
        overflow: hidden;
    }

    .btn-magnetic::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        transition: left 0.5s;
    }

    .btn-magnetic:hover::before {
        left: 100%;
    }

    .btn-magnetic:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

    /* Particle effect background */
    .particles {
        position: absolute;
        width: 100%;
        height: 100%;
        overflow: hidden;
        pointer-events: none;
    }

    .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: rgba(59, 130, 246, 0.3);
        border-radius: 50%;
        animation: float-particle 6s infinite linear;
    }

    @keyframes float-particle {
        0% {
            transform: translateY(100vh) rotate(0deg);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(-100px) rotate(360deg);
            opacity: 0;
        }
    }

    /* Staggered animation classes */
    .stagger-1 { animation-delay: 0.1s; }
    .stagger-2 { animation-delay: 0.2s; }
    .stagger-3 { animation-delay: 0.3s; }
    .stagger-4 { animation-delay: 0.4s; }
    .stagger-5 { animation-delay: 0.5s; }

    /* Simplified interactive elements */
    .interactive-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .interactive-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }

    /* Loading skeleton */
    @keyframes skeleton-loading {
        0% {
            background-position: -200px 0;
        }
        100% {
            background-position: calc(200px + 100%) 0;
        }
    }

    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200px 100%;
        animation: skeleton-loading 1.5s infinite;
    }

    /* Advanced Features */
    
    /* Cursor Trail Effect */
    .cursor-trail {
        position: fixed;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.3) 0%, transparent 70%);
        pointer-events: none;
        z-index: 9999;
        transition: all 0.1s ease;
    }

    /* Text Reveal Animation */
    @keyframes textReveal {
        0% {
            width: 0%;
        }
        100% {
            width: 100%;
        }
    }

    .text-reveal {
        position: relative;
        overflow: hidden;
    }

    .text-reveal::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 0%;
        height: 100%;
        background: #3b82f6;
        animation: textReveal 1.5s ease-out forwards;
        animation-delay: 0.5s;
    }

    /* Ripple Effect */
    .ripple {
        position: relative;
        overflow: hidden;
    }

    .ripple::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .ripple:hover::before {
        width: 300px;
        height: 300px;
    }

    /* Tilt Effect */
    .tilt-effect {
        transform-style: preserve-3d;
        transition: all 0.3s ease;
    }

    /* Spotlight Effect */
    .spotlight {
        position: relative;
        overflow: hidden;
    }

    .spotlight::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }

    .spotlight:hover::before {
        opacity: 1;
    }

    /* Simplified Breathing Animation - Disabled for performance */
    @keyframes breathing {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.01);
        }
    }

    .breathing {
        /* animation: breathing 6s ease-in-out infinite; */
    }

    /* Glitch Effect */
    @keyframes glitch {
        0%, 100% {
            transform: translate(0);
        }
        20% {
            transform: translate(-2px, 2px);
        }
        40% {
            transform: translate(-2px, -2px);
        }
        60% {
            transform: translate(2px, 2px);
        }
        80% {
            transform: translate(2px, -2px);
        }
    }

    .glitch:hover {
        animation: glitch 0.3s ease-in-out;
    }

    /* Liquid Button */
    .liquid-button {
        position: relative;
        overflow: hidden;
        border-radius: 50px;
    }

    .liquid-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        transition: left 0.5s ease;
    }

    .liquid-button:hover::before {
        left: 100%;
    }

    /* Performance optimizations */
    .gpu-accelerated {
        transform: translateZ(0);
        backface-visibility: hidden;
        perspective: 1000;
    }

    /* Reduce animations for low-performance devices */
    .reduce-animations * {
        animation-duration: 0.1s !important;
        transition-duration: 0.1s !important;
    }

    /* Dark mode styles (bonus feature) */
    .dark-mode {
        filter: invert(1) hue-rotate(180deg);
    }

    .dark-mode img,
    .dark-mode video,
    .dark-mode svg {
        filter: invert(1) hue-rotate(180deg);
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
        .glass-morphism {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #000;
        }
    }

    /* Print styles */
    @media print {
        .particles,
        .cursor-trail,
        .floating,
        .morphing {
            display: none !important;
        }
    }
</style>

<!-- Professional JavaScript Enhancements -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Intersection Observer for scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, observerOptions);

        // Observe all scroll-animate elements
        document.querySelectorAll('.scroll-animate').forEach(el => {
            observer.observe(el);
        });

        // Add enhanced classes to buttons
        document.querySelectorAll('button, a[class*="bg-"]').forEach(btn => {
            if (!btn.classList.contains('btn-enhanced')) {
                btn.classList.add('btn-enhanced');
            }
        });

        // Add testimonial card classes
        document.querySelectorAll('#testimonial .bg-white').forEach(card => {
            card.classList.add('testimonial-card');
        });

        // Add floating animation to badges
        document.querySelectorAll('[class*="badge"], [class*="rounded-full"]').forEach(badge => {
            if (badge.textContent.includes('Populer') || badge.textContent.includes('Gratis')) {
                badge.classList.add('float-animation');
            }
        });

        // Smooth reveal for pricing table
        const pricingTable = document.querySelector('#pricing .overflow-visible');
        if (pricingTable) {
            const pricingObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.transform = 'scale(1)';
                        entry.target.style.opacity = '1';
                    }
                });
            }, { threshold: 0.2 });

            pricingTable.style.transform = 'scale(0.95)';
            pricingTable.style.opacity = '0';
            pricingTable.style.transition = 'all 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            pricingObserver.observe(pricingTable);
        }

        // Add gradient text effect to main headline
        const headline = document.querySelector('h1');
        if (headline && headline.textContent.includes('Maksimalkan')) {
            headline.classList.add('gradient-text');
        }

        // Advanced Features
        
        // 1. Simplified Particle System - Reduced frequency
        function createParticles() {
            const heroSection = document.querySelector('#hero');
            if (!heroSection) return;

            const particlesContainer = document.createElement('div');
            particlesContainer.className = 'particles';
            heroSection.appendChild(particlesContainer);

            function createParticle() {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 3 + 5) + 's';
                particle.style.animationDelay = Math.random() * 2 + 's';
                particlesContainer.appendChild(particle);

                setTimeout(() => {
                    particle.remove();
                }, 8000);
            }

            // Create particles less frequently
            setInterval(createParticle, 2000);
        }

        // 2. Simplified Magnetic Button Effect
        function addMagneticEffect() {
            const buttons = document.querySelectorAll('button, a[class*="bg-"]');
            
            buttons.forEach(button => {
                button.classList.add('btn-magnetic');
                
                button.addEventListener('mousemove', (e) => {
                    const rect = button.getBoundingClientRect();
                    const x = e.clientX - rect.left - rect.width / 2;
                    const y = e.clientY - rect.top - rect.height / 2;
                    
                    button.style.transform = `translate(${x * 0.05}px, ${y * 0.05}px)`;
                });
                
                button.addEventListener('mouseleave', () => {
                    button.style.transform = 'translate(0, 0)';
                });
            });
        }

        // 3. Typing Effect for Headlines
        function addTypingEffect() {
            const subtitle = document.querySelector('h2');
            if (!subtitle) return;

            const text = subtitle.textContent;
            subtitle.textContent = '';
            subtitle.style.borderRight = '2px solid #3b82f6';
            subtitle.style.animation = 'blink 1s infinite';

            let i = 0;
            const typeWriter = () => {
                if (i < text.length) {
                    subtitle.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                } else {
                    subtitle.style.borderRight = 'none';
                    subtitle.style.animation = 'none';
                }
            };

            setTimeout(typeWriter, 1000);
        }

        // 4. Simplified Card Effect - Removed 3D rotation
        function add3DCardEffect() {
            const cards = document.querySelectorAll('.testimonial-card, .bg-white');
            
            cards.forEach(card => {
                card.classList.add('interactive-card');
                
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
        }

        // Number Counter Animation
        function animateNumbers() {
            const numbers = document.querySelectorAll('[data-count]');
            
            numbers.forEach(number => {
                const target = parseInt(number.getAttribute('data-count'));
                const increment = target / 50;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    number.textContent = Math.floor(current);
                    
                    if (current >= target) {
                        number.textContent = target;
                        clearInterval(timer);
                    }
                }, 30);
            });
        }

        // 5. Scroll Progress Indicator
        function addScrollProgress() {
            const progressBar = document.createElement('div');
            progressBar.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 0%;
                height: 3px;
                background: linear-gradient(90deg, #3b82f6, #1d4ed8);
                z-index: 9999;
                transition: width 0.1s ease;
            `;
            document.body.appendChild(progressBar);

            window.addEventListener('scroll', () => {
                const scrolled = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
                progressBar.style.width = scrolled + '%';
            });
        }

        // 6. Smooth Number Counter
        function animateNumbers() {
            const numbers = document.querySelectorAll('[data-count]');
            
            numbers.forEach(number => {
                const target = parseInt(number.getAttribute('data-count'));
                const increment = target / 100;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    number.textContent = Math.floor(current);
                    
                    if (current >= target) {
                        number.textContent = target;
                        clearInterval(timer);
                    }
                }, 20);
            });
        }

        // 7. Advanced Intersection Observer with Staggered Animations
        function enhancedScrollAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('animate');
                            
                            // Add staggered animation to child elements
                            const children = entry.target.querySelectorAll('*');
                            children.forEach((child, childIndex) => {
                                if (childIndex < 5) {
                                    child.classList.add(`stagger-${childIndex + 1}`);
                                }
                            });
                        }, index * 100);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.scroll-animate').forEach(el => {
                observer.observe(el);
            });
        }

        // 8. Cursor Trail Effect
        function addCursorTrail() {
            const trail = document.createElement('div');
            trail.className = 'cursor-trail';
            document.body.appendChild(trail);

            let mouseX = 0, mouseY = 0;
            let trailX = 0, trailY = 0;

            document.addEventListener('mousemove', (e) => {
                mouseX = e.clientX;
                mouseY = e.clientY;
            });

            function animateTrail() {
                trailX += (mouseX - trailX) * 0.1;
                trailY += (mouseY - trailY) * 0.1;
                
                trail.style.left = trailX - 10 + 'px';
                trail.style.top = trailY - 10 + 'px';
                
                requestAnimationFrame(animateTrail);
            }
            animateTrail();
        }

        // 9. Ripple Effect on Buttons
        function addRippleEffect() {
            const buttons = document.querySelectorAll('button, a[class*="bg-"]');
            
            buttons.forEach(button => {
                button.classList.add('ripple');
                
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple-animation 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add ripple animation keyframes
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // 10. Simplified Tilt Effect - Removed excessive rotation
        function addTiltEffect() {
            const cards = document.querySelectorAll('.testimonial-card, .interactive-card');
            
            cards.forEach(card => {
                card.classList.add('tilt-effect');
                
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'scale(1.02)';
                });
                
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'scale(1)';
                });
            });
        }

        // 11. Text Reveal Animation
        function addTextRevealAnimation() {
            const headings = document.querySelectorAll('h1, h2');
            
            headings.forEach((heading, index) => {
                if (index < 3) { // Only first 3 headings
                    heading.classList.add('text-reveal');
                }
            });
        }

        // 12. Spotlight Effect
        function addSpotlightEffect() {
            const cards = document.querySelectorAll('.bg-white, .testimonial-card');
            
            cards.forEach(card => {
                card.classList.add('spotlight');
            });
        }

        // 13. Performance Monitor
        function addPerformanceMonitor() {
            let fps = 0;
            let lastTime = performance.now();
            
            function calculateFPS() {
                const now = performance.now();
                fps = Math.round(1000 / (now - lastTime));
                lastTime = now;
                
                // If FPS drops below 30, reduce animations
                if (fps < 30) {
                    document.body.classList.add('reduce-animations');
                } else {
                    document.body.classList.remove('reduce-animations');
                }
                
                requestAnimationFrame(calculateFPS);
            }
            
            calculateFPS();
        }

        // 14. Lazy Loading for Heavy Animations
        function lazyLoadAnimations() {
            const heavyElements = document.querySelectorAll('.particle, .morphing');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-heavy');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            
            heavyElements.forEach(el => observer.observe(el));
        }

        // 15. Dark Mode Toggle (Bonus)
        function addDarkModeToggle() {
            const toggle = document.createElement('button');
            toggle.innerHTML = '🌙';
            toggle.className = 'fixed bottom-4 left-4 w-12 h-12 bg-gray-800 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 z-50';
            toggle.style.display = 'none'; // Hidden by default
            
            toggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                toggle.innerHTML = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
            });
            
            document.body.appendChild(toggle);
        }

        // Fix clickability issues first
        function fixClickabilityIssues() {
            // Force all interactive elements to be clickable
            document.querySelectorAll('a, button, [role="button"], input, select, textarea').forEach(el => {
                el.style.position = 'relative';
                el.style.zIndex = '9999';
                el.style.pointerEvents = 'auto';
                
                // Add click event listener to ensure it works
                if (!el.hasAttribute('data-click-fixed')) {
                    el.addEventListener('click', function(e) {
                        // Ensure the click event propagates
                        e.stopPropagation();
                        
                        // For links, ensure navigation works
                        if (this.tagName === 'A' && this.href) {
                            window.location.href = this.href;
                        }
                    });
                    el.setAttribute('data-click-fixed', 'true');
                }
            });

            // Fix background elements
            document.querySelectorAll('.absolute, .gradient-orb, [class*="gradient"]::before, [class*="gradient"]::after').forEach(el => {
                el.style.pointerEvents = 'none';
                el.style.zIndex = '1';
            });
        }

        // Initialize essential features
        setTimeout(() => {
            // Fix clickability first
            fixClickabilityIssues();
            
            // Enable essential animations
            add3DCardEffect();
            addScrollProgress();
            enhancedScrollAnimations();
            addPerformanceMonitor();
            addMagneticEffect(); // Re-enable for button interactions
            addRippleEffect(); // Re-enable for button feedback
            addTiltEffect(); // Re-enable for card interactions
            
            // Add number counter animation
            animateNumbers();
            
            // Add smooth hover effects to all interactive elements
            document.querySelectorAll('button, a[href]').forEach(el => {
                el.style.transition = 'all 0.3s ease';
                el.addEventListener('mouseenter', () => {
                    el.style.transform = 'translateY(-1px)';
                });
                el.addEventListener('mouseleave', () => {
                    el.style.transform = 'translateY(0)';
                });
            });
            
            // Add testimonial card animations
            document.querySelectorAll('#testimonial .bg-gradient-to-br').forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
            
            // Add pricing table hover effects
            document.querySelectorAll('.pricing-card').forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-3px)';
                    card.style.boxShadow = '0 8px 25px rgba(0,0,0,0.12)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                    card.style.boxShadow = '';
                });
            });

            // Re-run clickability fix after animations
            setTimeout(fixClickabilityIssues, 1000);
        }, 500);

        // Add GPU acceleration to animated elements
        document.addEventListener('DOMContentLoaded', () => {
            const animatedElements = document.querySelectorAll('[class*="animate"], [class*="transition"]');
            animatedElements.forEach(el => el.classList.add('gpu-accelerated'));
            
            // Debug: Log all clickable elements
            console.log('Clickable elements found:', document.querySelectorAll('a, button').length);
            
            // Force fix all clickable elements
            document.querySelectorAll('a, button').forEach((el, index) => {
                el.style.position = 'relative';
                el.style.zIndex = '9999';
                el.style.pointerEvents = 'auto';
                
                // Add visual debug (remove in production)
                el.addEventListener('mouseenter', () => {
                    el.style.outline = '1px solid red';
                });
                el.addEventListener('mouseleave', () => {
                    el.style.outline = 'none';
                });
                
                console.log(`Fixed element ${index + 1}:`, el.tagName, el.textContent?.trim());
            });
        });

        // Subtle parallax effect (disabled to prevent layout issues)
        // Uncomment if needed, but may cause section overlaps
        /*
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.parallax-element');
            
            parallaxElements.forEach(element => {
                const speed = 0.1;
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
        */
    });
</script>

<!-- Footer -->
<footer class="relative w-full bg-gray-900 text-white overflow-visible">
    <!-- Background Pattern -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23374151" fill-opacity="0.05"%3E%3Ccircle cx="30" cy="30" r="1"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-40"></div>
    
    <!-- Gradient Overlay -->
    <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900"></div>
    
    <!-- Content -->
    <div class="relative z-10 mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
            <!-- Company Info -->
            <div class="lg:col-span-1">
                <div class="flex items-center mb-6">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                        <span class="text-white font-bold text-lg">X</span>
                    </div>
                    <span class="text-2xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">XpressPOS</span>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed mb-6 max-w-sm">
                    Sistem Point of Sale terdepan dengan teknologi AI untuk memaksimalkan potensi bisnis Anda. Kelola toko, restoran, dan bisnis dengan mudah dan efisien.
                </p>
                
                <!-- Social Media -->
                <div class="flex space-x-4">
                    <a href="https://www.tiktok.com/@xpresspos" target="_blank" rel="noopener noreferrer" 
                       class="group w-11 h-11 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-gradient-to-br hover:from-pink-500 hover:to-red-500 transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <svg class="w-5 h-5 transition-colors duration-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                        </svg>
                    </a>
                    <a href="https://www.instagram.com/xpresspos.id" target="_blank" rel="noopener noreferrer"
                       class="group w-11 h-11 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-gradient-to-br hover:from-purple-500 hover:to-pink-500 transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <svg class="w-5 h-5 transition-colors duration-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </a>
                    <a href="https://www.youtube.com/@xpresspos" target="_blank" rel="noopener noreferrer"
                       class="group w-11 h-11 bg-gray-800 rounded-xl flex items-center justify-center hover:bg-gradient-to-br hover:from-red-500 hover:to-red-600 transition-all duration-300 transform hover:scale-110 hover:shadow-lg">
                        <svg class="w-5 h-5 transition-colors duration-300" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div>
                <h3 class="text-lg font-semibold mb-6 text-white">Quick Links</h3>
                <ul class="space-y-4">
                    <li><a href="#hero" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Beranda
                    </a></li>
                    <li><a href="#features" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Fitur
                    </a></li>
                    <li><a href="#pricing" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Harga
                    </a></li>
                    <li><a href="#testimonial" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Testimoni
                    </a></li>
                    <li><a href="{{ route('login') }}" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-blue-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Login
                    </a></li>
                </ul>
            </div>

            <!-- Products & Services -->
            <div>
                <h3 class="text-lg font-semibold mb-6 text-white">Produk & Layanan</h3>
                <ul class="space-y-4">
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        POS System
                    </a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Inventory Management
                    </a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Monitoring Dashboard
                    </a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm flex items-center group">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></span>
                        Multi-Branches
                    </a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div>
                <h3 class="text-lg font-semibold mb-6 text-white">Hubungi Kami</h3>
                <div class="space-y-4">
                    <div class="flex items-center group">
                        <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center mr-3 group-hover:bg-blue-600 transition-colors duration-300">
                            <svg class="w-5 h-5 text-blue-400 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <a href="mailto:support@xpresspos.id" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm block">
                                support@xpresspos.id
                            </a>
                            <span class="text-xs text-gray-500">Email Support</span>
                        </div>
                    </div>
                    <div class="flex items-center group">
                        <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center mr-3 group-hover:bg-green-600 transition-colors duration-300">
                            <svg class="w-5 h-5 text-green-400 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <div>
                            <a href="tel:+6281234567890" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm block">
                                +62 812-3456-7890
                            </a>
                            <span class="text-xs text-gray-500">Customer Service</span>
                        </div>
                    </div>
                    <div class="flex items-center group">
                        <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center mr-3 group-hover:bg-purple-600 transition-colors duration-300">
                            <svg class="w-5 h-5 text-purple-400 group-hover:text-white transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-gray-400 text-sm block">24/7 Support</span>
                            <span class="text-xs text-gray-500">Selalu Siap Membantu</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Section -->
        <div class="border-t border-gray-800 mt-12 pt-8">
            <div class="flex flex-col lg:flex-row justify-between items-center space-y-4 lg:space-y-0">
                <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-6">
                    <p class="text-gray-400 text-sm">
                        © {{ date('Y') }} XpressPOS. All rights reserved.
                    </p>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm relative group">
                            Privacy Policy
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-blue-500 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm relative group">
                            Syarat & Ketentuan
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-blue-500 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition-colors duration-300 text-sm relative group">
                            Cookie Policy
                            <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-blue-500 transition-all duration-300 group-hover:w-full"></span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-gray-400 text-sm">Made with</span>
                    <svg class="w-4 h-4 text-red-500 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-gray-400 text-sm">in Indonesia</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button -->
<button id="scrollToTop" class="fixed bottom-4 right-4 w-12 h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 opacity-0 invisible z-50" style="position: fixed !important; bottom: 16px !important; right: 16px !important; z-index: 9999 !important;">
    <svg class="w-6 h-6 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
    </svg>
</button>

<script>
    // Scroll to top functionality
    document.addEventListener('DOMContentLoaded', function() {
        const scrollToTopBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.remove('opacity-0', 'invisible');
                scrollToTopBtn.classList.add('opacity-100', 'visible');
            } else {
                scrollToTopBtn.classList.add('opacity-0', 'invisible');
                scrollToTopBtn.classList.remove('opacity-100', 'visible');
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
</script>

@endsection
