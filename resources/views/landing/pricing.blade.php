@extends('layouts.xpresspos')

@section('title', 'Harga - XpressPOS')
@section('description', 'Pilih paket XpressPOS yang sesuai dengan kebutuhan bisnis Anda')

@section('content')
<main class="overflow-hidden">
    <!-- Hero Section -->
    <section class="relative w-full hero-gradient">
        <!-- Gradient Orbs -->
        <div class="gradient-orb gradient-orb-1" style="pointer-events: none; z-index: 1;"></div>
        <div class="gradient-orb gradient-orb-2" style="pointer-events: none; z-index: 1;"></div>
        
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Header -->
                <div class="text-center mb-12 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Pilih Paket Terbaik
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Dapatkan harga terbaik sesuai kebutuhan bisnis Anda
                    </p>
                    
                    <!-- Billing Toggle -->
                    <div class="flex justify-center mt-8">
                        <div class="bg-gray-100 p-1 rounded-lg inline-flex relative">
                            <button id="monthly-btn" class="billing-toggle active px-6 py-3 text-sm font-medium rounded-md transition-all duration-300">
                                Bulanan
                            </button>
                            <button id="yearly-btn" class="billing-toggle px-6 py-3 text-sm font-medium rounded-md transition-all duration-300">
                                Tahunan 
                                <span class="ml-1 px-2 py-0.5 text-xs bg-red-500 text-white rounded-full">Hemat 2 Bulan</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- No steps shown on pricing page -->

                <!-- Pricing Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    @foreach($plans as $index => $plan)
                    <div class="pricing-card {{ $index === 1 ? 'popular' : '' }} bg-white rounded-2xl shadow-xl border-2 {{ $index === 1 ? 'border-blue-500' : 'border-gray-200' }} p-8 relative transform hover:-translate-y-2 transition-all duration-300">
                        @if($index === 1)
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                            <span class="bg-blue-500 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">
                                Paling Populer
                            </span>
                        </div>
                        @endif
                        
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h3>
                            <p class="text-gray-600 mb-6">{{ $plan->description }}</p>
                            
                            <!-- Price -->
                            <div class="mb-8">
                                <div class="monthly-price">
                                    <span class="text-4xl font-bold text-blue-600">Rp {{ number_format($plan->price, 0, ',', '.') }}</span>
                                    <span class="text-gray-600">/bulan</span>
                                </div>
                                <div class="yearly-price hidden">
                                    <span class="text-4xl font-bold text-blue-600">Rp {{ number_format($plan->annual_price, 0, ',', '.') }}</span>
                                    <span class="text-gray-600">/tahun</span>
                                    <div class="text-sm text-green-600 mt-1">
                                        Hemat Rp {{ number_format(($plan->price * 12) - $plan->annual_price, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features -->
                            <ul class="text-left space-y-3 mb-8">
                                @foreach($plan->features as $feature)
                                <li class="flex items-center">
                                    <svg class="w-5 h-5 text-green-500 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ ucwords(str_replace('_', ' ', $feature)) }}
                                </li>
                                @endforeach
                            </ul>
                            
                            <!-- CTA Button (Dynamic based on auth & current plan) -->
                            @php
                                // Default values
                                $buttonLabel = 'Pilih Paket ' . $plan->name;
                                $buttonDisabled = false;
                                $buttonAction = true; // Can click
                                
                                // Default color: gray untuk basic, blue untuk popular (Pro)
                                if ($index === 1) {
                                    $buttonClass = 'from-blue-600 to-blue-700'; // Popular plan
                                } else {
                                    $buttonClass = 'from-gray-600 to-gray-700'; // Default
                                }
                                
                                // Dynamic button ONLY for authenticated users with active plan
                                if (isset($currentPlan) && $currentPlan) {
                                    if ($plan->id === $currentPlan->id) {
                                        // Current plan - TETAP GUNAKAN WARNA ASLI (blue/gray)
                                        $buttonLabel = 'Paket Saat Ini ✓';
                                        $buttonDisabled = true;
                                        $buttonAction = false;
                                        // Warna tetap sama, hanya tambah opacity
                                    } elseif ($plan->sort_order > $currentPlan->sort_order) {
                                        // Upgrade - TETAP GUNAKAN WARNA ASLI
                                        $buttonLabel = 'Upgrade ke ' . $plan->name;
                                        // Warna tetap dari default (blue/gray)
                                    } elseif ($plan->sort_order < $currentPlan->sort_order) {
                                        // Downgrade - TETAP GUNAKAN WARNA ASLI
                                        $buttonLabel = 'Downgrade ke ' . $plan->name;
                                        // Warna tetap dari default (blue/gray)
                                    }
                                }
                            @endphp
                            
                            <button 
                                @if($buttonAction) onclick="selectPlan({{ $plan->id }}, '{{ $plan->slug }}')" @endif
                                @if($buttonDisabled) disabled @endif
                                class="w-full bg-gradient-to-r {{ $buttonClass }} text-white py-4 px-6 rounded-xl font-semibold transition-all duration-300 {{ $buttonDisabled ? 'opacity-60 cursor-not-allowed' : 'hover:shadow-xl transform hover:-translate-y-0.5 hover:scale-105' }}">
                                {{ $buttonLabel }}
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- FAQ Section -->
                <div class="mt-20 max-w-4xl mx-auto">
                    <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Pertanyaan Umum</h2>
                    
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Apakah ada trial gratis?</h3>
                            <p class="text-gray-600">Ya, semua paket mendapat trial gratis 14 hari tanpa perlu kartu kredit.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Bisakah upgrade/downgrade paket?</h3>
                            <p class="text-gray-600">Tentu saja! Anda bisa upgrade atau downgrade paket kapan saja sesuai kebutuhan bisnis.</p>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Metode pembayaran apa saja yang diterima?</h3>
                            <p class="text-gray-600">Kami menerima transfer bank, e-wallet (OVO, DANA, LinkAja), QRIS, dan kartu kredit.</p>
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
}

.gradient-orb-2 {
    width: 300px;
    height: 300px;
    background: linear-gradient(45deg, #ec4899, #10b981);
    bottom: -150px;
    right: -150px;
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

.billing-toggle.active {
    background: white;
    color: #1f2937;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.billing-toggle:not(.active) {
    color: #6b7280;
}

.pricing-card.popular {
    transform: scale(1.05);
}
</style>

<script>
let currentBilling = 'monthly';

document.getElementById('monthly-btn').addEventListener('click', function() {
    switchBilling('monthly');
});

document.getElementById('yearly-btn').addEventListener('click', function() {
    switchBilling('yearly');
});

function switchBilling(billing) {
    currentBilling = billing;
    
    // Update toggle buttons
    document.querySelectorAll('.billing-toggle').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById(billing + '-btn').classList.add('active');
    
    // Update prices
    document.querySelectorAll('.monthly-price').forEach(el => {
        el.style.display = billing === 'monthly' ? 'block' : 'none';
    });
    document.querySelectorAll('.yearly-price').forEach(el => {
        el.style.display = billing === 'yearly' ? 'block' : 'none';
    });
}

function selectPlan(planId, planSlug) {
    // Use relative URL for local development, absolute URL for production
    const baseUrl = '{{ app()->environment("local") ? "/checkout" : route("landing.checkout") }}';
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('plan_id', planId); // Primary: plan_id (integer)
    url.searchParams.set('plan', planSlug); // Secondary: slug (for backward compatibility)
    url.searchParams.set('billing', currentBilling);
    window.location.href = url.toString();
}
</script>
@endsection