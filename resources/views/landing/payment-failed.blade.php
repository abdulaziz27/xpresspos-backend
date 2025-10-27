@extends('layouts.xpresspos')

@section('title', 'Pembayaran Gagal - XpressPOS')
@section('description', 'Pembayaran gagal. Silakan coba lagi atau hubungi support')

@section('content')
<main class="overflow-hidden">
    <!-- Failed Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-4xl px-6">
                <!-- Failed Animation -->
                <div class="text-center mb-16 animate-fade-in-up">
                    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-8">
                        <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl font-bold text-red-600 mb-4">
                        Pembayaran Gagal
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Maaf, terjadi kesalahan saat memproses pembayaran Anda
                    </p>
                </div>

                <!-- Failed Content -->
                <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    @if($subscription)
                    <!-- Order Details -->
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Detail Pesanan</h2>
                        <div class="bg-red-50 rounded-xl p-6">
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
                                    <p class="text-2xl font-bold text-red-600">Rp {{ number_format($subscription->payment_amount, 0, ',', '.') }}</p>
                                    <p class="text-sm text-red-600">✗ Pembayaran Gagal</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Possible Reasons -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Kemungkinan Penyebab</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-yellow-50 rounded-xl p-6 border border-yellow-200">
                                <h3 class="font-semibold text-yellow-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Masalah Teknis
                                </h3>
                                <ul class="text-sm text-yellow-800 space-y-1">
                                    <li>• Koneksi internet tidak stabil</li>
                                    <li>• Timeout saat memproses pembayaran</li>
                                    <li>• Gangguan sistem payment gateway</li>
                                </ul>
                            </div>

                            <div class="bg-orange-50 rounded-xl p-6 border border-orange-200">
                                <h3 class="font-semibold text-orange-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Masalah Pembayaran
                                </h3>
                                <ul class="text-sm text-orange-800 space-y-1">
                                    <li>• Saldo tidak mencukupi</li>
                                    <li>• Kartu kredit expired atau diblokir</li>
                                    <li>• Limit transaksi terlampaui</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                        @if($subscription)
                        <a href="{{ route('landing.payment') }}?subscription_id={{ $subscription->id }}&invoice_id={{ $subscription->xendit_invoice_id }}" 
                           class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Coba Lagi
                        </a>
                        @endif
                        
                        <a href="{{ route('landing.checkout') }}{{ $subscription ? '?plan=' . $subscription->plan_id . '&billing=' . $subscription->billing_cycle : '' }}" 
                           class="inline-flex items-center justify-center px-8 py-4 bg-white border-2 border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-all duration-300">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali ke Checkout
                        </a>
                    </div>

                    <!-- Alternative Payment Methods -->
                    <div class="border-t border-gray-200 pt-6 mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Metode Pembayaran Alternatif</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <svg class="w-8 h-8 text-blue-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <p class="text-sm font-medium text-blue-900">Transfer Bank</p>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <svg class="w-8 h-8 text-green-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                                <p class="text-sm font-medium text-green-900">E-Wallet</p>
                            </div>
                            <div class="text-center p-4 bg-purple-50 rounded-lg">
                                <svg class="w-8 h-8 text-purple-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                </svg>
                                <p class="text-sm font-medium text-purple-900">QRIS</p>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <svg class="w-8 h-8 text-red-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <p class="text-sm font-medium text-red-900">Kartu Kredit</p>
                            </div>
                        </div>
                    </div>

                    <!-- Support Info -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="bg-blue-50 rounded-xl p-6">
                            <h3 class="font-semibold text-blue-900 mb-3">Butuh Bantuan?</h3>
                            <p class="text-sm text-blue-800 mb-4">
                                Tim support kami siap membantu Anda menyelesaikan masalah pembayaran ini.
                            </p>
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
        rgba(239, 68, 68, 0.05) 0%, 
        rgba(156, 163, 175, 0.05) 50%, 
        rgba(239, 68, 68, 0.05) 100%
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
</style>
@endsection