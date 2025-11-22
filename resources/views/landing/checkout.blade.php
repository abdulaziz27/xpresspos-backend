@extends('layouts.xpresspos')

@section('title', 'Checkout - XpressPOS')
@section('description', 'Lengkapi data untuk berlangganan XpressPOS')

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
                        Checkout Subscription
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Review dan selesaikan pembelian paket XpressPOS Anda
                    </p>
                </div>

                <!-- Progress Steps -->
                <x-payment-steps :currentStep="1" />

                <!-- Checkout Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <!-- Cart Section -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-xl p-8">
                            <h2 class="text-2xl font-semibold text-gray-900 mb-8 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13l-1.5 6m0 0h9M6 19a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4z"></path>
                                </svg>
                                Keranjang Anda
                            </h2>
                            
                            <!-- Selected Package Display -->
                            <div class="mb-8">
                                <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="w-16 h-16 bg-blue-600 rounded-xl flex items-center justify-center mr-4">
                                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m4 0V9a2 2 0 012-2h2a2 2 0 012 2v12"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900">{{ $plan->name }}</h3>
                                                <p class="text-gray-600">Paket berlangganan XpressPOS</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            @if(isset($original_price) && $original_price != $price)
                                            <p class="text-sm text-gray-400 line-through mb-1">Rp {{ number_format($original_price, 0, ',', '.') }}</p>
                                            @endif
                                            <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($price, 0, ',', '.') }}</p>
                                            <p class="text-sm text-gray-600">{{ $billing === 'yearly' ? 'per tahun' : 'per bulan' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Duration Selection -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Durasi Paket</h3>
                                <form id="duration-form" action="{{ route('landing.checkout') }}" method="GET">
                                    <input type="hidden" name="plan" value="{{ $planId }}">
                                    <select name="billing" onchange="updateDuration()" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <option value="monthly" {{ $billing === 'monthly' ? 'selected' : '' }}>
                                            1 Bulan - Rp {{ number_format($plan->price, 0, ',', '.') }}/bulan
                                        </option>
                                        <option value="yearly" {{ $billing === 'yearly' ? 'selected' : '' }}>
                                            12 Bulan - Rp {{ number_format($plan->annual_price, 0, ',', '.') }}/tahun 
                                            (Hemat Rp {{ number_format(($plan->price * 12) - $plan->annual_price, 0, ',', '.') }})
                                        </option>
                                    </select>
                                </form>
                            </div>

                            <!-- Package Features -->
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Fitur yang Anda Dapatkan</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($plan->features as $feature)
                                    <div class="flex items-center p-3 bg-green-50 rounded-lg">
                                        <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="text-gray-700">{{ ucwords(str_replace('_', ' ', $feature)) }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Coupon Section -->
                            <div class="border-t border-gray-200 pt-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Kode Kupon Diskon</h3>
                                <div class="flex gap-3">
                                    <input type="text" id="coupon-code" placeholder="Masukkan kode kupon" 
                                           value="{{ $coupon_code ?? '' }}"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                    <button type="button" onclick="applyCoupon()" 
                                            class="px-6 py-3 bg-gray-600 text-white rounded-lg font-semibold hover:bg-gray-700 transition-colors cursor-pointer">
                                        Terapkan
                                    </button>
                                </div>
                                <div id="coupon-message" class="mt-2 text-sm hidden"></div>
                                <div id="coupon-active-display" class="mt-2 p-3 bg-green-50 rounded-lg border border-green-200" style="display: none;">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-sm font-medium text-green-800" id="coupon-code-text"></span>
                                            <p class="text-xs text-green-600" id="coupon-discount-text"></p>
                                        </div>
                                        <button type="button" onclick="removeCoupon()" class="text-red-600 hover:text-red-700 text-sm underline ml-4 cursor-pointer">
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Navigation Buttons -->
                            <div class="pt-6 flex flex-col sm:flex-row gap-4 justify-between">
                                <!-- Back Button -->
                                <a href="{{ route('landing.pricing') }}" 
                                   class="flex items-center justify-center px-6 py-4 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:border-gray-400 hover:bg-gray-50 transition-all duration-300">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Kembali ke Pilih Paket
                                </a>
                                
                                <!-- Continue Button -->
                                <form action="{{ route('landing.checkout.step2') }}" method="GET" id="checkout-form" onsubmit="return submitCheckoutForm(event)">
                                    <input type="hidden" name="plan_id" value="{{ $planId }}">
                                    <input type="hidden" name="billing" value="{{ $billing }}">
                                    <button type="submit" 
                                            class="w-full flex-1 bg-gradient-to-r cursor-pointer from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                                        <span class="flex items-center justify-center">
                                            Lanjutkan ke Informasi Bisnis
                                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                            </svg>
                                        </span>
                                    </button>
                                </form>
                            </div>
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
                                    <div>
                                        @if(isset($original_price) && $original_price != $price)
                                        <div class="text-sm text-gray-400 line-through">Rp {{ number_format($original_price, 0, ',', '.') }}</div>
                                        @endif
                                        <span class="text-2xl font-bold text-blue-600">Rp {{ number_format($price, 0, ',', '.') }}</span>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ $billing === 'yearly' ? '/tahun' : '/bulan' }}</span>
                                </div>
                                @if(isset($coupon) && $discount_amount > 0)
                                <div class="mt-2 p-2 bg-green-50 rounded-lg border border-green-200">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-green-700 font-medium">Kupon {{ $coupon['code'] }} diterapkan</span>
                                        <span class="text-green-600 font-semibold">-{{ number_format($coupon['discount_percentage'] ?? 0, 0) }}%</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                            
                            <!-- Price Breakdown -->
                            <div class="space-y-4 mb-8">
                                <div class="flex justify-between py-3">
                                    <span class="text-gray-600">Subtotal</span>
                                    <span class="font-semibold text-gray-900 subtotal-amount">Rp {{ number_format($original_price ?? $price, 0, ',', '.') }}</span>
                                </div>
                                @if(isset($coupon) && $discount_amount > 0)
                                <div class="flex justify-between py-3 border-t border-gray-200" id="discount-row">
                                    <span class="text-gray-600">
                                        Diskon Kupon 
                                        <span class="text-xs text-gray-500">({{ $coupon['code'] }})</span>
                                    </span>
                                    <span class="font-semibold text-green-600">-Rp {{ number_format($discount_amount, 0, ',', '.') }}</span>
                                </div>
                                @endif
                                <!-- <div class="flex justify-between py-3 border-t border-gray-200">
                                    <span class="text-gray-600">PPN (11%)</span>
                                    <span class="font-semibold text-gray-900">Rp {{ number_format($tax, 0, ',', '.') }}</span>
                                </div> -->
                                <div class="border-t-2 border-gray-300 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xl font-bold text-gray-900">Total</span>
                                        <span class="text-3xl font-bold text-blue-600 total-amount">Rp {{ number_format($total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="font-semibold text-gray-900 mb-3">Yang Anda Dapatkan:</h4>
                                <ul class="space-y-2">
                                    @foreach($plan->features as $feature)
                                    <li class="flex items-center text-sm text-gray-600">
                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {{ ucwords(str_replace('_', ' ', $feature)) }}
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

/* Checkout step 1 styles */
</style>

<script>
let appliedCoupon = @json($coupon ?? null);
let originalPrice = {{ $original_price ?? $price }};
let originalTotal = {{ $total }};
let currentCouponCode = @json($coupon_code ?? null);

// If coupon is already applied, update the display
@if(isset($coupon) && $discount_amount > 0)
document.addEventListener('DOMContentLoaded', function() {
    const couponData = {
        discount_amount: {{ $discount_amount }},
        final_amount: {{ $total }},
        discount_percentage: {{ $coupon['discount_percentage'] ?? 0 }},
        code: '{{ $coupon['code'] }}'
    };
    updatePriceDisplay(couponData);
    showCouponActiveDisplay(couponData);
});
@endif

function updateDuration() {
    document.getElementById('duration-form').submit();
}

async function applyCoupon() {
    const couponCode = document.getElementById('coupon-code').value.trim();
    const messageDiv = document.getElementById('coupon-message');
    const applyButton = document.querySelector('button[onclick="applyCoupon()"]');
    
    if (!couponCode) {
        showCouponMessage('Masukkan kode kupon terlebih dahulu', 'error');
        return;
    }
    
    // Disable button during validation
    applyButton.disabled = true;
    applyButton.textContent = 'Memvalidasi...';
    
    try {
        const response = await fetch('{{ route("landing.checkout.validate-coupon") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                code: couponCode,
                plan_id: {{ $planId }},
                billing_cycle: '{{ $billing }}',
                amount: originalPrice
            })
        });
        
        const data = await response.json();
        
        if (data.valid) {
            appliedCoupon = {
                code: data.code,
                discount_amount: data.discount_amount,
                final_amount: data.final_amount,
                discount_percentage: data.discount_percentage,
                voucher_id: data.voucher_id
            };
            
            updatePriceDisplay(data);
            
            // Show coupon active display (this replaces the success message)
            showCouponActiveDisplay(data);
            
            // Hide the success message since the box already shows the info
            document.getElementById('coupon-message').classList.add('hidden');
            
            // Store coupon in hidden input for form submission
            let couponInput = document.getElementById('coupon-code-hidden');
            if (!couponInput) {
                couponInput = document.createElement('input');
                couponInput.type = 'hidden';
                couponInput.id = 'coupon-code-hidden';
                couponInput.name = 'coupon_code';
                const checkoutForm = document.getElementById('checkout-form');
                if (checkoutForm) {
                    checkoutForm.appendChild(couponInput);
                }
            }
            couponInput.value = data.code;
        } else {
            appliedCoupon = null;
            resetPriceDisplay();
            showCouponMessage(data.error || 'Kode kupon tidak valid', 'error');
        }
    } catch (error) {
        console.error('Error validating coupon:', error);
        showCouponMessage('Terjadi kesalahan saat memvalidasi kupon', 'error');
    } finally {
        applyButton.disabled = false;
        applyButton.textContent = 'Terapkan';
    }
}

function updatePriceDisplay(couponData) {
    // Update subtotal display
    const subtotalElement = document.querySelector('.subtotal-amount');
    if (subtotalElement) {
        subtotalElement.textContent = `Rp ${formatCurrency(originalPrice)}`;
    }
    
    // Add or update discount display
    let discountRow = document.getElementById('discount-row');
    if (!discountRow) {
        const priceBreakdown = document.querySelector('.space-y-4.mb-8');
        if (priceBreakdown) {
            discountRow = document.createElement('div');
            discountRow.id = 'discount-row';
            discountRow.className = 'flex justify-between py-3 border-t border-gray-200';
            discountRow.innerHTML = `
                <span class="text-gray-600">Diskon Kupon</span>
                <span class="font-semibold text-green-600">-Rp ${formatCurrency(couponData.discount_amount)}</span>
            `;
            // Insert before total
            const totalRow = priceBreakdown.querySelector('.border-t-2');
            if (totalRow) {
                priceBreakdown.insertBefore(discountRow, totalRow);
            }
        }
    } else {
        discountRow.querySelector('.text-green-600').textContent = `-Rp ${formatCurrency(couponData.discount_amount)}`;
    }
    
    // Update total
    const totalElement = document.querySelector('.total-amount');
    if (totalElement) {
        totalElement.textContent = `Rp ${formatCurrency(couponData.final_amount)}`;
    }
    
    // Update main price display
    const mainPriceElements = document.querySelectorAll('.text-2xl.font-bold.text-blue-600, .text-3xl.font-bold.text-blue-600');
    mainPriceElements.forEach(el => {
        if (el.textContent.includes('Rp')) {
            el.textContent = `Rp ${formatCurrency(couponData.final_amount)}`;
        }
    });
}

function resetPriceDisplay() {
    // Remove discount row
    const discountRow = document.getElementById('discount-row');
    if (discountRow) {
        discountRow.remove();
    }
    
    // Reset total
    const totalElement = document.querySelector('.total-amount');
    if (totalElement) {
        totalElement.textContent = `Rp ${formatCurrency(originalTotal)}`;
    }
    
    // Reset main price display
    const mainPriceElements = document.querySelectorAll('.text-2xl.font-bold.text-blue-600, .text-3xl.font-bold.text-blue-600');
    mainPriceElements.forEach(el => {
        if (el.textContent.includes('Rp')) {
            el.textContent = `Rp ${formatCurrency(originalPrice)}`;
        }
    });
}

function showCouponActiveDisplay(couponData) {
    // Use the single coupon display element (always exists in DOM)
    const couponDisplay = document.getElementById('coupon-active-display');
    
    if (couponDisplay) {
        // Update the content
        const codeText = couponDisplay.querySelector('#coupon-code-text');
        const discountText = couponDisplay.querySelector('#coupon-discount-text');
        
        if (codeText) {
            codeText.textContent = `Kupon ${couponData.code} aktif`;
        }
        if (discountText) {
            discountText.textContent = `Diskon ${couponData.discount_percentage}% (Rp ${formatCurrency(couponData.discount_amount)})`;
        }
        
        // Show the element
        couponDisplay.style.display = 'block';
    }
}

function hideCouponActiveDisplay() {
    // Hide the coupon display
    const couponDisplay = document.getElementById('coupon-active-display');
    if (couponDisplay) {
        couponDisplay.style.display = 'none';
    }
}

function removeCoupon() {
    appliedCoupon = null;
    document.getElementById('coupon-code').value = '';
    resetPriceDisplay();
    showCouponMessage('', 'success');
    document.getElementById('coupon-message').classList.add('hidden');
    hideCouponActiveDisplay();
    
    // Remove hidden input
    const couponInput = document.getElementById('coupon-code-hidden');
    if (couponInput) {
        couponInput.remove();
    }
}

function showCouponMessage(message, type) {
    const messageDiv = document.getElementById('coupon-message');
    messageDiv.textContent = message;
    messageDiv.className = `mt-2 text-sm ${type === 'success' ? 'text-green-600' : 'text-red-600'}`;
    messageDiv.classList.remove('hidden');
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID').format(Math.round(amount));
}

function submitCheckoutForm(event) {
    // Add coupon code to form if applied
    if (appliedCoupon) {
        const couponInput = document.createElement('input');
        couponInput.type = 'hidden';
        couponInput.name = 'coupon_code';
        couponInput.value = appliedCoupon.code;
        event.target.appendChild(couponInput);
    }
    return true;
}
</script>
@endsection