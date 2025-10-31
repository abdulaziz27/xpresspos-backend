@extends('layouts.xpresspos')

@section('title', 'Pilih Metode Pembayaran - XpressPOS')
@section('description', 'Pilih metode pembayaran untuk berlangganan XpressPOS')

@section('content')
<main class="overflow-hidden">
    <section class="relative w-full hero-gradient">
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-4xl px-6">
                <!-- Progress Steps -->
                <x-payment-steps :currentStep="3" />

                <!-- Header -->
                <div class="text-center mb-8 animate-fade-in-up">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        Pilih Metode Pembayaran
                    </h1>
                    <p class="text-lg text-gray-600">
                        Pilih metode pembayaran yang paling nyaman untuk Anda
                    </p>
                </div>

                <!-- Order Summary -->
                <div class="bg-white rounded-2xl shadow-lg p-6 mb-8 animate-fade-in-up" style="animation-delay: 0.1s">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Ringkasan Pesanan</h2>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Paket:</span>
                        <span class="font-semibold">XpressPOS {{ isset($plan) ? ucfirst($plan->name) : ucfirst($planId ?? 'N/A') }}</span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-gray-600">Billing:</span>
                        <span class="font-semibold">{{ isset($subscription) ? ucfirst(json_decode($subscription->meta, true)['billing_cycle'] ?? 'monthly') : ucfirst($billing ?? 'monthly') }}</span>
                    </div>
                    <div class="border-t border-gray-200 my-4"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-900">Total:</span>
                        <span class="text-2xl font-bold text-blue-600">Rp {{ number_format($amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Metode Pembayaran</h2>
                    
                    <form action="{{ isset($subscription) ? route('landing.payment.process') : route('landing.checkout.step3.process') }}" method="POST">
                        @csrf
                        @if(isset($subscription))
                            <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                        @endif
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                            <!-- Bank Transfer -->
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method" value="bank_transfer" class="sr-only peer" required>
                                <div class="p-6 border-2 border-gray-200 rounded-xl cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-all">
                                    <div class="flex items-center mb-3">
                                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Bank Transfer</h3>
                                            <p class="text-sm text-gray-600">Virtual Account</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>• BCA, BNI, BRI, Mandiri</p>
                                        <p>• BSI, Permata</p>
                                        <p>• Konfirmasi otomatis</p>
                                    </div>
                                </div>
                            </label>

                            <!-- E-Wallet -->
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method" value="e_wallet" class="sr-only peer">
                                <div class="p-6 border-2 border-gray-200 rounded-xl cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-all">
                                    <div class="flex items-center mb-3">
                                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">E-Wallet</h3>
                                            <p class="text-sm text-gray-600">Dompet Digital</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>• OVO, DANA, LinkAja</p>
                                        <p>• ShopeePay</p>
                                        <p>• Pembayaran instan</p>
                                    </div>
                                </div>
                            </label>

                            <!-- QRIS -->
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method" value="qris" class="sr-only peer">
                                <div class="p-6 border-2 border-gray-200 rounded-xl cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-all">
                                    <div class="flex items-center mb-3">
                                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">QRIS</h3>
                                            <p class="text-sm text-gray-600">Scan QR Code</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>• Pembayaran QR universal</p>
                                        <p>• Semua e-wallet didukung</p>
                                        <p>• Konfirmasi instan</p>
                                    </div>
                                </div>
                            </label>

                            <!-- Credit Card -->
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method" value="credit_card" class="sr-only peer">
                                <div class="p-6 border-2 border-gray-200 rounded-xl cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-300 transition-all">
                                    <div class="flex items-center mb-3">
                                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">Kartu Kredit</h3>
                                            <p class="text-sm text-gray-600">Credit/Debit Card</p>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 space-y-1">
                                        <p>• Visa, Mastercard, JCB</p>
                                        <p>• Aman dan terpercaya</p>
                                        <p>• Pembayaran instan</p>
                                    </div>
                                </div>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold text-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                            Lanjutkan ke Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
.hero-gradient {
    background: linear-gradient(135deg, 
        rgba(59, 130, 246, 0.05) 0%, 
        rgba(147, 51, 234, 0.05) 50%, 
        rgba(59, 130, 246, 0.05) 100%
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
