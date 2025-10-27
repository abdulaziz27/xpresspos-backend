@extends('layouts.xpresspos')

@section('title', 'Pilih Metode Pembayaran - XpressPOS')
@section('description', 'Pilih metode pembayaran untuk berlangganan XpressPOS')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Header -->
                <div class="text-center mb-12 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Pilih Metode Pembayaran
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Pilih metode pembayaran yang paling nyaman untuk Anda
                    </p>
                </div>

                <!-- Progress Steps -->
                <x-payment-steps :currentStep="3" />

                <!-- Payment Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <!-- Payment Methods Section -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-xl p-8">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                Metode Pembayaran
                            </h2>
                            
                            @if($errors->any())
                            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex">
                                    <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <h3 class="text-sm font-medium text-red-800">Terjadi kesalahan:</h3>
                                        <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                                            @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <form action="{{ route('landing.checkout.step3.process') }}" method="POST" class="space-y-6">
                                @csrf
                                
                                <!-- Payment Methods Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @foreach($paymentMethods as $key => $method)
                                    <label class="payment-method-card cursor-pointer">
                                        <input type="radio" name="payment_method" value="{{ $key }}" class="sr-only payment-method-input" required>
                                        <div class="payment-method-content p-6 border-2 border-gray-200 rounded-xl hover:border-blue-300 transition-all duration-300">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="flex items-center">
                                                    @if($method['icon'] === 'bank')
                                                    <svg class="w-8 h-8 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m4 0V9a2 2 0 012-2h2a2 2 0 012 2v12"></path>
                                                    </svg>
                                                    @elseif($method['icon'] === 'wallet')
                                                    <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                    @elseif($method['icon'] === 'qr-code')
                                                    <svg class="w-8 h-8 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                                    </svg>
                                                    @else
                                                    <svg class="w-8 h-8 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                    @endif
                                                    <div>
                                                        <h3 class="font-semibold text-gray-900">{{ $method['name'] }}</h3>
                                                        <p class="text-sm text-gray-600">{{ $method['description'] }}</p>
                                                    </div>
                                                </div>
                                                <div class="payment-method-check w-6 h-6 border-2 border-gray-300 rounded-full flex items-center justify-center">
                                                    <div class="w-3 h-3 bg-blue-600 rounded-full opacity-0 transition-opacity duration-200"></div>
                                                </div>
                                            </div>
                                            
                                            @if($key === 'bank_transfer')
                                            <div class="flex space-x-2 mt-3">
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">BCA</span>
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">BNI</span>
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">BRI</span>
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">Mandiri</span>
                                            </div>
                                            @elseif($key === 'e_wallet')
                                            <div class="flex space-x-2 mt-3">
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">OVO</span>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">DANA</span>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">LinkAja</span>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">ShopeePay</span>
                                            </div>
                                            @elseif($key === 'credit_card')
                                            <div class="flex space-x-2 mt-3">
                                                <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded">Visa</span>
                                                <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded">Mastercard</span>
                                                <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded">JCB</span>
                                            </div>
                                            @endif
                                        </div>
                                    </label>
                                    @endforeach
                                </div>

                                <!-- Customer Information Summary -->
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Pelanggan</h3>
                                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Nama:</span>
                                            <span class="font-medium">{{ $checkoutData['name'] }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Email:</span>
                                            <span class="font-medium">{{ $checkoutData['email'] }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Bisnis:</span>
                                            <span class="font-medium">{{ $checkoutData['business_name'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Navigation Buttons -->
                                <div class="pt-6 flex flex-col sm:flex-row gap-4">
                                    <!-- Back Button -->
                                    <a href="{{ route('landing.checkout.step2') }}?plan={{ $planId }}&billing={{ $billing }}" 
                                       class="flex items-center justify-center px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:border-gray-400 hover:bg-gray-50 transition-all duration-300">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                        </svg>
                                        Kembali
                                    </a>
                                    
                                    <!-- Continue Button -->
                                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                                        <span class="flex items-center justify-center">
                                            Bayar Sekarang
                                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-2xl shadow-xl p-8 sticky top-8">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                                Total Pembayaran
                            </h2>
                            
                            <!-- Selected Plan -->
                            <div class="mb-6 p-4 bg-blue-50 rounded-xl border border-blue-200">
                                <h3 class="font-bold text-gray-900 text-lg">XpressPOS {{ ucfirst($checkoutData['plan_id']) }}</h3>
                                <p class="text-sm text-gray-600 mb-2">Paket {{ ucfirst($checkoutData['billing_cycle']) }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold text-blue-600">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                                    <span class="text-sm text-gray-600">{{ $checkoutData['billing_cycle'] === 'yearly' ? '/tahun' : '/bulan' }}</span>
                                </div>
                            </div>
                            
                            <!-- Price Breakdown -->
                            <div class="space-y-4 mb-8">
                                <div class="flex justify-between py-3">
                                    <span class="text-gray-600">Subtotal</span>
                                    <span class="font-semibold text-gray-900">Rp {{ number_format($amount * 0.9009, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-t border-gray-200">
                                    <span class="text-gray-600">PPN (11%)</span>
                                    <span class="font-semibold text-gray-900">Rp {{ number_format($amount * 0.0991, 0, ',', '.') }}</span>
                                </div>
                                <div class="border-t-2 border-gray-300 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xl font-bold text-gray-900">Total</span>
                                        <span class="text-3xl font-bold text-blue-600">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Badge -->
                            <div class="p-4 bg-green-50 rounded-xl border border-green-200">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    <span class="text-sm text-green-800 font-medium">Pembayaran Aman & Terjamin</span>
                                </div>
                                <p class="text-xs text-green-700 mt-1">Powered by Xendit - Payment Gateway Terpercaya</p>
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

.payment-method-input:checked + .payment-method-content {
    border-color: #2563eb;
    background-color: #eff6ff;
}

.payment-method-input:checked + .payment-method-content .payment-method-check {
    border-color: #2563eb;
}

.payment-method-input:checked + .payment-method-content .payment-method-check > div {
    opacity: 1;
}
</style>
@endsection