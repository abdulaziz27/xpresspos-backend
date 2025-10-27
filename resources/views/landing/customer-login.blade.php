@extends('layouts.xpresspos')

@section('title', 'Customer Dashboard - XpressPOS')
@section('description', 'Akses dashboard customer untuk tracking subscription XpressPOS')

@section('content')
<main class="overflow-hidden">
    <!-- Login Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-md px-6">
                <!-- Header -->
                <div class="text-center mb-12 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Customer Dashboard
                    </h1>
                    <p class="text-xl text-gray-600">
                        Masukkan email untuk mengakses dashboard Anda
                    </p>
                </div>

                <!-- Login Form -->
                <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <form action="{{ route('landing.customer.dashboard') }}" method="GET" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="masukkan@email.anda">
                        </div>

                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-4 px-6 rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl">
                            Akses Dashboard
                        </button>
                    </form>

                    <!-- Info -->
                    <div class="mt-6 p-4 bg-blue-50 rounded-xl border border-blue-200">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm text-blue-800 font-medium mb-1">Informasi</p>
                                <p class="text-xs text-blue-700">Gunakan email yang sama dengan yang Anda gunakan saat berlangganan XpressPOS</p>
                            </div>
                        </div>
                    </div>

                    <!-- Back to Home -->
                    <div class="mt-6 text-center">
                        <a href="{{ route('landing.main') }}" 
                           class="text-blue-600 hover:text-blue-700 font-medium transition-colors duration-300 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali ke Beranda
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
</style>
@endsection