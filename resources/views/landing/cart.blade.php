@extends('layouts.xpresspos')

@section('title', 'Keranjang Belanja - XpressPOS')
@section('description', 'Kelola pesanan Anda dengan mudah menggunakan sistem POS XpressPOS')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full hero-gradient">
        <!-- Gradient Orbs -->
        <div class="gradient-orb gradient-orb-1" style="pointer-events: none; z-index: 1;"></div>
        <div class="gradient-orb gradient-orb-2" style="pointer-events: none; z-index: 1;"></div>
        
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.02"%3E%3Ccircle cx="30" cy="30" r="1"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-40" style="pointer-events: none; z-index: 1;"></div>
        
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Header -->
                <div class="text-center mb-16 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Konfirmasi Pesanan
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Tinjau paket yang Anda pilih sebelum melanjutkan pembayaran
                    </p>
                </div>

                <!-- Cart Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <!-- Cart Items -->
                    <div class="lg:col-span-2">
                        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                Item Pesanan
                            </h2>
                            
                            <!-- Selected Plan -->
                            <div class="space-y-6">
                                <!-- Selected Tier -->
                                <div class="p-8 border-2 border-blue-200 rounded-2xl bg-gradient-to-br from-blue-50 to-white shadow-lg">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-6">
                                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="flex items-center mb-2">
                                                    <h3 class="font-bold text-gray-900 text-xl mr-3">XpressPOS Professional</h3>
                                                    <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">TERPILIH</span>
                                                </div>
                                                <p class="text-gray-600 mb-3">Paket lengkap untuk bisnis berkembang</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">✓ Multi-Location</span>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">✓ Advanced Analytics</span>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">✓ API Access</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-gray-900 text-2xl">Rp 599.000</p>
                                            <p class="text-sm text-gray-600">per bulan</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="lg:col-span-1">
                        <div class="glass-effect rounded-2xl p-8 shadow-2xl sticky top-8">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Ringkasan Pesanan
                            </h2>
                            
                            <div class="space-y-4 mb-8">
                                <div class="flex justify-between py-3">
                                    <span class="text-gray-600">XpressPOS Professional</span>
                                    <span class="font-semibold text-gray-900">Rp 599.000</span>
                                </div>
                                <div class="flex justify-between py-3 border-t border-gray-200">
                                    <span class="text-gray-600">PPN (11%)</span>
                                    <span class="font-semibold text-gray-900">Rp 65.890</span>
                                </div>
                                <div class="border-t-2 border-gray-300 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xl font-bold text-gray-900">Total Pembayaran</span>
                                        <div class="text-right">
                                            <span class="text-3xl font-bold text-blue-600">Rp 664.890</span>
                                            <p class="text-sm text-gray-600">per bulan</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl mb-4 flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                Lanjutkan Pembayaran
                            </button>

                            <div class="text-center">
                                <a href="{{ route('home') }}" class="text-blue-600 hover:text-blue-700 font-medium transition-colors duration-300 flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Kembali ke Beranda
                                </a>
                            </div>

                            <!-- Billing Info -->
                            <div class="mt-8 p-4 bg-blue-50 rounded-xl border border-blue-200">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <p class="text-sm text-blue-800 font-medium mb-1">Informasi Pembayaran</p>
                                        <p class="text-xs text-blue-700">• Pembayaran bulanan otomatis</p>
                                        <p class="text-xs text-blue-700">• Dapat dibatalkan kapan saja</p>
                                        <p class="text-xs text-blue-700">• Gratis trial 14 hari</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Badge -->
                            <div class="mt-4 p-4 bg-green-50 rounded-xl border border-green-200">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    <span class="text-sm text-green-800 font-medium">Pembayaran Aman & Terjamin</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.hero-gradient {
    background: linear-gradient(135deg, 
        rgba(59, 130, 246, 0.05) 0%, 
        rgba(147, 51, 234, 0.05) 25%, 
        rgba(236, 72, 153, 0.05) 50%, 
        rgba(59, 130, 246, 0.05) 75%, 
        rgba(16, 185, 129, 0.05) 100%
    );
}

.gradient-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(120px);
    opacity: 0.3;
    animation: float 20s ease-in-out infinite;
}

.gradient-orb-1 {
    width: 400px;
    height: 400px;
    background: linear-gradient(45deg, #3b82f6, #8b5cf6);
    top: -200px;
    left: -200px;
    animation-delay: 0s;
}

.gradient-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(45deg, #ec4899, #10b981);
    bottom: -150px;
    right: -150px;
    animation-delay: -10s;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-30px) rotate(120deg); }
    66% { transform: translateY(20px) rotate(240deg); }
}

.animate-fade-in-up {
    animation: fadeInUp 0.8s ease-out forwards;
    opacity: 0;
    transform: translateY(30px);
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.glass-effect {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}
</style>
@endsection