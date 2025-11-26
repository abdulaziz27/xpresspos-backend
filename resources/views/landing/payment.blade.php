@extends('layouts.xpresspos')

@section('title', 'Pembayaran - XpressPOS')
@section('description', 'Selesaikan pembayaran untuk berlangganan XpressPOS')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-4xl px-6">
                <!-- Header -->
                <div class="text-center mb-12 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Selesaikan Pembayaran
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Pilih metode pembayaran untuk menyelesaikan langganan Anda
                    </p>
                </div>

                <!-- Progress Steps -->
                <x-payment-steps :currentStep="3" />

                <!-- Payment Content -->
                <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <!-- Order Summary -->
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Detail Pesanan</h2>
                        <div class="bg-blue-50 rounded-xl p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ $subscription->business_name }}</h3>
                                    <p class="text-gray-600">{{ $subscription->name }} ({{ $subscription->email }})</p>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Paket: XpressPOS {{ ucfirst($subscription->plan_id) }} - {{ ucfirst($subscription->billing_cycle) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($subscription->payment_amount, 0, ',', '.') }}</p>
                                    <p class="text-sm text-gray-600">{{ $subscription->billing_cycle === 'yearly' ? 'per tahun' : 'per bulan' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div id="payment-status" class="mb-8">
                        @if($errors->any())
                            <div class="flex items-center justify-center p-6 bg-red-50 rounded-xl border border-red-200 mb-6">
                                <svg class="w-6 h-6 text-red-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-red-800 font-medium">{{ $errors->first() }}</span>
                            </div>
                        @else
                            <div class="flex items-center justify-center p-6 bg-yellow-50 rounded-xl border border-yellow-200">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-yellow-600 mr-3"></div>
                                <span class="text-yellow-800 font-medium">Memuat informasi pembayaran...</span>
                            </div>
                        @endif
                    </div>

                    <!-- Payment Methods Form -->
                    <div id="payment-methods" class="hidden">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Pilih Metode Pembayaran</h2>
                        
                        <form action="{{ route('landing.payment.process') }}" method="POST" id="payment-form">
                            @csrf
                            <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                            <input type="hidden" name="payment_method" id="selected-payment-method">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                                @foreach($paymentMethods as $key => $method)
                                <button type="button" onclick="selectPaymentMethod('{{ $key }}')" 
                                        class="payment-method-btn p-6 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all duration-300 text-left">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                            @if($method['icon'] === 'bank')
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                            @elseif($method['icon'] === 'wallet')
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                            @elseif($method['icon'] === 'qr-code')
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                            </svg>
                                            @else
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">{{ $method['name'] }}</h3>
                                            <p class="text-sm text-gray-600">{{ $method['description'] }}</p>
                                        </div>
                                    </div>
                                </button>
                                @endforeach
                            </div>

                            <!-- Submit Button -->
                            <div id="submit-section" class="hidden">
                                <button type="submit" class="w-full bg-blue-600 text-white py-4 px-6 rounded-xl font-semibold hover:bg-blue-700 transition-colors duration-300">
                                    Lanjutkan ke Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="border-t border-gray-200 pt-6">
                        <div class="bg-blue-50 rounded-xl p-6">
                            <h3 class="font-semibold text-blue-900 mb-3">Informasi Penting:</h3>
                            <ul class="text-sm text-blue-800 space-y-2">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Pembayaran akan diproses secara real-time
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Akun XpressPOS akan aktif otomatis setelah pembayaran berhasil
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Anda akan menerima email konfirmasi dan panduan setup
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Gratis trial 30 hari untuk semua paket
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <div class="mt-8 text-center">
                        <a href="{{ route('landing.checkout') }}?plan={{ $subscription->plan_id }}&billing={{ $subscription->billing_cycle }}" 
                           class="text-blue-600 hover:text-blue-700 font-medium transition-colors duration-300 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali ke Checkout
                        </a>
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

.payment-method-btn.selected {
    border-color: #3b82f6;
    background-color: #eff6ff;
}
</style>

<script>
// Load payment page
document.addEventListener('DOMContentLoaded', function() {
    // Check payment status and show payment methods
    checkPaymentStatus();
});

function checkPaymentStatus() {
    // For now, just show payment methods directly
    // In the future, we can add status checking here if needed
    document.getElementById('payment-status').classList.add('hidden');
    document.getElementById('payment-methods').classList.remove('hidden');
}

function selectPaymentMethod(method) {
    // Remove previous selection
    document.querySelectorAll('.payment-method-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Add selection to clicked button
    event.currentTarget.classList.add('selected');
    
    // Set the selected payment method
    document.getElementById('selected-payment-method').value = method;
    
    // Show submit button
    document.getElementById('submit-section').classList.remove('hidden');
}
</script>
@endsection