@extends('layouts.xpresspos')

@section('title', 'Pembayaran Berhasil - XpressPOS')
@section('description', 'Pembayaran berhasil! Akun XpressPOS Anda sudah aktif')

@section('content')
<main class="overflow-hidden">
    <!-- Success Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-4xl px-6">
                <!-- Success Animation -->
                <div class="text-center mb-12 animate-fade-in-up">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-8 animate-bounce">
                        <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl font-bold text-green-600 mb-4">
                        Pembayaran Berhasil!
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Selamat! Akun XpressPOS Anda sudah aktif dan siap digunakan
                    </p>
                </div>

                <!-- Progress Steps - All Completed -->
                <x-payment-steps :currentStep="4" />

                <!-- Development Notice -->
                @if(session('dev_notice'))
                <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-6 mb-8 animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-yellow-800">Development Mode</h3>
                            <p class="text-yellow-700 text-sm mt-1">{{ session('dev_notice') }}</p>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Success Content -->
                <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    @if($subscription)
                    <!-- Order Details -->
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Detail Pesanan</h2>
                        <div class="bg-green-50 rounded-xl p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">{{ $subscription->business_name }}</h3>
                                    <p class="text-gray-600">{{ $subscription->name }}</p>
                                    <p class="text-gray-600">{{ $subscription->email }}</p>
                                    <p class="text-gray-600">{{ $subscription->phone }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600 mb-1">Paket: XpressPOS {{ ucfirst($subscription->plan_id) }}</p>
                                    <p class="text-sm text-gray-600 mb-2">Billing: {{ ucfirst($subscription->billing_cycle) }}</p>
                                    <p class="text-2xl font-bold text-green-600">Rp {{ number_format($subscription->payment_amount, 0, ',', '.') }}</p>
                                    <p class="text-sm text-green-600">✓ Pembayaran Berhasil</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Login Information (New User) -->
                    @if(isset($showLoginInfo) && $showLoginInfo && isset($temporaryPassword))
                    <div class="mb-8 bg-blue-50 border border-blue-200 rounded-2xl p-6">
                        <h2 class="text-xl font-semibold text-blue-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-6 6c-2.072 0-3.927-.777-5.302-2.052M15 7a2 2 0 00-2 2m2-2v2a2 2 0 01-2 2m0 0V9a2 2 0 012-2m-2 2H9m6 0a2 2 0 01-2 2H9m0 0a2 2 0 01-2-2m2 2v2a2 2 0 01-2 2H7m2 0v2a2 2 0 002 2v-2a2 2 0 00-2-2H7m2 0H9m0 0H7m2 0v2m0-2a2 2 0 00-2-2H5m2 2H7m0 0H5m2 0a2 2 0 002 2v-2z"></path>
                            </svg>
                            🎉 Akun Baru Anda Sudah Dibuat!
                        </h2>
                        
                        <div class="bg-white rounded-xl p-4 mb-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Informasi Login:</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-mono bg-gray-100 px-2 py-1 rounded">{{ $subscription->email }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Password Sementara:</span>
                                    <span class="font-mono bg-yellow-100 px-2 py-1 rounded text-yellow-800">{{ $temporaryPassword }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm text-yellow-800 font-medium">Penting untuk Keamanan</p>
                                    <p class="text-sm text-yellow-700">Segera ganti password sementara setelah login pertama kali. Kami juga telah mengirim email dengan informasi login lengkap.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @elseif(isset($showLoginInfo) && $showLoginInfo && !isset($temporaryPassword))
                    <!-- Existing User Renewal -->
                    <div class="mb-8 bg-green-50 border border-green-200 rounded-2xl p-6">
                        <h2 class="text-xl font-semibold text-green-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            ✅ Langganan Berhasil Diperpanjang!
                        </h2>
                        
                        <div class="bg-white rounded-xl p-4 mb-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Akses Dashboard:</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="font-mono bg-gray-100 px-2 py-1 rounded">{{ $subscription->email }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Password:</span>
                                    <span class="text-gray-600">Gunakan password yang sudah ada</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <p class="text-sm text-green-800 font-medium">Akun Existing</p>
                                    <p class="text-sm text-green-700">Gunakan akun dan password yang sudah ada untuk login. Semua data toko Anda tetap tersimpan dengan aman.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Next Steps -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Langkah Selanjutnya</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Step 1 -->
                            <div class="text-center p-6 bg-blue-50 rounded-xl">
                                <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-white font-bold">1</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Cek Email</h3>
                                <p class="text-sm text-gray-600">Kami telah mengirim email konfirmasi dengan detail akun dan panduan setup</p>
                            </div>

                            <!-- Step 2 -->
                            <div class="text-center p-6 bg-green-50 rounded-xl">
                                <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-white font-bold">2</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Login Dashboard Owner</h3>
                                <p class="text-sm text-gray-600">Dashboard untuk mengelola toko, produk, karyawan, dan laporan bisnis</p>
                            </div>

                            <!-- Step 3 -->
                            <div class="text-center p-6 bg-purple-50 rounded-xl">
                                <div class="w-12 h-12 bg-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-white font-bold">3</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Mulai Jualan</h3>
                                <p class="text-sm text-gray-600">Setup selesai! Mulai gunakan XpressPOS untuk bisnis Anda</p>
                            </div>
                        </div>
                    </div>

                    <!-- Dashboard Explanation -->
                    <div class="mb-8 bg-gray-50 rounded-xl p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Perbedaan Dashboard</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="font-semibold text-blue-900 mb-2">🏪 Dashboard Owner</h4>
                                <ul class="text-sm text-blue-800 space-y-1">
                                    <li>• Kelola produk dan inventory</li>
                                    <li>• Proses pesanan dan transaksi</li>
                                    <li>• Laporan penjualan dan analitik</li>
                                    <li>• Manajemen karyawan</li>
                                    <li>• Setting toko dan sistem</li>
                                </ul>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <h4 class="font-semibold text-green-900 mb-2">📋 Status Langganan</h4>
                                <ul class="text-sm text-green-800 space-y-1">
                                    <li>• Status pembayaran langganan</li>
                                    <li>• Riwayat transaksi pembayaran</li>
                                    <li>• Tanggal perpanjangan</li>
                                    <li>• Download invoice</li>
                                    <li>• Update informasi billing</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ config('app.owner_url', '/owner') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            Buka Dashboard Owner
                        </a>
                        
                        @if($subscription)
                        <a href="/customer-dashboard?email={{ $subscription->email }}" 
                           class="inline-flex items-center justify-center px-8 py-4 bg-white border-2 border-green-600 text-green-600 font-semibold rounded-xl hover:bg-green-50 transition-all duration-300"
                           title="Dashboard untuk melihat status langganan dan riwayat pembayaran">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Lihat Status Langganan
                        </a>
                        @endif
                    </div>

                    <!-- Support Info -->
                    <div class="mt-8 border-t border-gray-200 pt-6">
                        <div class="bg-blue-50 rounded-xl p-6">
                            <h3 class="font-semibold text-blue-900 mb-3">Butuh Bantuan?</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div class="flex items-center text-blue-800">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    support@xpresspos.id
                                </div>
                                <div class="flex items-center text-blue-800">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    +62 21 1234 5678
                                </div>
                                <div class="flex items-center text-blue-800">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                    Live Chat 24/7
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
        rgba(34, 197, 94, 0.05) 0%, 
        rgba(59, 130, 246, 0.05) 50%, 
        rgba(34, 197, 94, 0.05) 100%
    );
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

@keyframes bounce {
    0%, 20%, 53%, 80%, 100% {
        transform: translate3d(0,0,0);
    }
    40%, 43% {
        transform: translate3d(0,-30px,0);
    }
    70% {
        transform: translate3d(0,-15px,0);
    }
    90% {
        transform: translate3d(0,-4px,0);
    }
}

.animate-bounce {
    animation: bounce 2s infinite;
}
</style>

<script>
// Auto-redirect to dashboard after 10 seconds
setTimeout(function() {
    const dashboardUrl = '{{ config("app.owner_url", "/owner") }}';
    if (confirm('Redirect ke dashboard owner dalam 5 detik. Klik OK untuk redirect sekarang atau Cancel untuk tetap di halaman ini.')) {
        window.location.href = dashboardUrl;
    }
}, 10000);

// Confetti animation (optional)
document.addEventListener('DOMContentLoaded', function() {
    // Simple confetti effect
    createConfetti();
});

function createConfetti() {
    const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
    const confettiCount = 50;
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.zIndex = '9999';
            confetti.style.pointerEvents = 'none';
            confetti.style.borderRadius = '50%';
            
            document.body.appendChild(confetti);
            
            const animation = confetti.animate([
                { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
                { transform: `translateY(100vh) rotate(${Math.random() * 360}deg)`, opacity: 0 }
            ], {
                duration: Math.random() * 3000 + 2000,
                easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            });
            
            animation.onfinish = () => confetti.remove();
        }, i * 100);
    }
}
</script>
@endsection