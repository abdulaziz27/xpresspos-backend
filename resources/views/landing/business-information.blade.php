@extends('layouts.xpresspos')

@section('title', 'Informasi Bisnis - XpressPOS')
@section('description', 'Lengkapi informasi bisnis untuk berlangganan XpressPOS')

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
                        Informasi Bisnis
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Lengkapi data bisnis Anda untuk melanjutkan proses berlangganan
                    </p>
                </div>

                <!-- Progress Steps -->
                <x-payment-steps :currentStep="2" />

                <!-- Checkout Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <!-- Form Section -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-xl p-8">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m4 0V9a2 2 0 012-2h2a2 2 0 012 2v12"></path>
                                </svg>
                                Alamat Penagihan & Informasi Bisnis
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

                            <form id="business-info-form" method="POST" class="space-y-6">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $planId }}">
                                <input type="hidden" name="billing_cycle" value="{{ $billing }}">
                                
                                <!-- Personal Info -->
                                <div class="mb-8">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Kontak</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                                            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        </div>
                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                            <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon *</label>
                                        <div class="flex gap-2">
                                            <select id="country_code" name="country_code" class="w-32 px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors bg-gray-50">
                                                <option value="+62" selected>🇮🇩 +62</option>
                                                <option value="+60">🇲🇾 +60</option>
                                                <option value="+65">🇸🇬 +65</option>
                                                <option value="+66">🇹🇭 +66</option>
                                                <option value="+84">🇻🇳 +84</option>
                                                <option value="+63">🇵🇭 +63</option>
                                            </select>
                                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required
                                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                                   placeholder="85211xxxxx"
                                                   pattern="[0-9]{8,12}"
                                                   title="Masukkan nomor telepon tanpa 0 di depan (contoh: 85211xxxxx)">
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">Masukkan nomor tanpa 0 di depan. Contoh: 85211xxxxx</p>
                                    </div>                                </div>

                                <!-- Business Info -->
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Informasi Bisnis</h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="business_name" class="block text-sm font-medium text-gray-700 mb-2">Nama Bisnis *</label>
                                            <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}" required
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        </div>
                                        <div>
                                            <label for="business_type" class="block text-sm font-medium text-gray-700 mb-2">Jenis Bisnis *</label>
                                            <select id="business_type" name="business_type" required
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                                <option value="">Pilih jenis bisnis</option>
                                                <option value="restaurant" {{ old('business_type') == 'restaurant' ? 'selected' : '' }}>Restoran/Cafe</option>
                                                <option value="retail" {{ old('business_type') == 'retail' ? 'selected' : '' }}>Retail/Toko</option>
                                                <option value="grocery" {{ old('business_type') == 'grocery' ? 'selected' : '' }}>Minimarket/Grocery</option>
                                                <option value="fashion" {{ old('business_type') == 'fashion' ? 'selected' : '' }}>Fashion/Clothing</option>
                                                <option value="beauty" {{ old('business_type') == 'beauty' ? 'selected' : '' }}>Kecantikan/Salon</option>
                                                <option value="electronics" {{ old('business_type') == 'electronics' ? 'selected' : '' }}>Elektronik</option>
                                                <option value="other" {{ old('business_type') == 'other' ? 'selected' : '' }}>Lainnya</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terms -->
                                <div class="border-t border-gray-200 pt-6">
                                    <label class="flex items-start">
                                        <input type="checkbox" required class="mt-1 mr-3 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="text-sm text-gray-600">
                                            Saya setuju dengan <a href="#" class="text-blue-600 hover:underline">Syarat & Ketentuan</a> 
                                            dan <a href="#" class="text-blue-600 hover:underline">Kebijakan Privasi</a> XpressPOS
                                        </span>
                                    </label>
                                </div>

                                <!-- Navigation Buttons -->
                                <div class="pt-6 flex flex-col sm:flex-row gap-4">
                                    <!-- Back Button -->
                                    <button type="button" onclick="goBackToCheckout('{{ $planId }}', '{{ $billing }}')" 
                                       class="flex items-center justify-center px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:border-gray-400 hover:bg-gray-50 transition-all duration-300">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                        </svg>
                                        Kembali ke Keranjang
                                    </a>
                                    
                                    <!-- Continue Button -->
                                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                                        <span class="flex items-center justify-center">
                                            Proses Pembayaran
                                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
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
                                Ringkasan Pesanan
                            </h2>
                            
                            <!-- Selected Plan -->
                            <div class="mb-6 p-4 bg-blue-50 rounded-xl border border-blue-200">
                                <h3 class="font-bold text-gray-900 text-lg">{{ $plan['name'] }}</h3>
                                <p class="text-sm text-gray-600 mb-2">Paket {{ ucfirst($billing) }}</p>
                                <div class="flex items-center justify-between">
                                    <span class="text-2xl font-bold text-blue-600">Rp {{ number_format($price, 0, ',', '.') }}</span>
                                    <span class="text-sm text-gray-600">{{ $billing === 'yearly' ? '/tahun' : '/bulan' }}</span>
                                </div>
                            </div>
                            
                            <!-- Price Breakdown -->
                            <div class="space-y-4 mb-8">
                                <div class="flex justify-between py-3">
                                    <span class="text-gray-600">Subtotal</span>
                                    <span class="font-semibold text-gray-900">Rp {{ number_format($price, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-t border-gray-200">
                                    <span class="text-gray-600">PPN (11%)</span>
                                    <span class="font-semibold text-gray-900">Rp {{ number_format($tax, 0, ',', '.') }}</span>
                                </div>
                                <div class="border-t-2 border-gray-300 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xl font-bold text-gray-900">Total</span>
                                        <span class="text-3xl font-bold text-blue-600">Rp {{ number_format($total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="font-semibold text-gray-900 mb-3">Yang Anda Dapatkan:</h4>
                                <ul class="space-y-2">
                                    @foreach($plan['features'] as $feature)
                                    <li class="flex items-center text-sm text-gray-600">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {{ $feature }}
                                    </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Security Badge -->
                            <div class="mt-6 p-4 bg-green-50 rounded-xl border border-green-200">
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submission
    const form = document.getElementById('business-info-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Set form action to local URL
            const url = new URL('/checkout/business-info', window.location.origin);
            form.action = url.toString();
            
            // Submit the form
            form.submit();
        });
    }
});

function goBackToCheckout(planId, billing) {
    // Build checkout URL using current domain
    const url = new URL('/checkout', window.location.origin);
    url.searchParams.set('plan', planId);
    url.searchParams.set('billing', billing);
    
    // Navigate back to checkout
    window.location.href = url.toString();
}
</script>
@endsection