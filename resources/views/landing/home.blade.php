@extends('landing.layout')

@section('title', 'XpressPOS - Solusi POS Terdepan untuk Bisnis Anda')

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">
                Revolusi Bisnis Anda dengan <span class="text-yellow-400">XpressPOS</span>
            </h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">
                Sistem Point of Sale yang powerful, mudah digunakan, dan terjangkau untuk semua jenis bisnis
            </p>
            <div class="space-x-4">
                @auth
                    @if(app()->environment('production') && env('OWNER_URL'))
                        <a href="{{ env('OWNER_URL') }}" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                            Buka Dashboard
                        </a>
                    @else
                        <a href="/owner-panel" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                            Buka Dashboard
                        </a>
                    @endif
                @else
                    <a href="{{ route('landing.register') }}" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                        Mulai Gratis
                    </a>
                    <a href="{{ route('landing.login') }}" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-800 transition duration-300">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Mengapa Memilih XpressPOS?
            </h2>
            <p class="text-xl text-gray-600">
                Fitur lengkap yang dirancang khusus untuk kebutuhan bisnis Indonesia
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-lg shadow-md">
                <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-3">Super Cepat</h3>
                <p class="text-gray-600">Proses transaksi dalam hitungan detik dengan interface yang intuitif</p>
            </div>

            <div class="bg-white p-8 rounded-lg shadow-md">
                <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-3">Laporan Real-time</h3>
                <p class="text-gray-600">Monitor performa bisnis Anda dengan dashboard analytics yang komprehensif</p>
            </div>

            <div class="bg-white p-8 rounded-lg shadow-md">
                <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-3">Keamanan Terjamin</h3>
                <p class="text-gray-600">Data bisnis Anda aman dengan enkripsi tingkat enterprise</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="bg-blue-600 text-white py-16">
    <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl md:text-4xl font-bold mb-4">
            Siap Mengembangkan Bisnis Anda?
        </h2>
        <p class="text-xl mb-8 text-blue-100">
            Bergabunglah dengan ribuan bisnis yang telah mempercayai XpressPOS
        </p>
        @auth
            @if(app()->environment('production') && env('OWNER_URL'))
                <a href="{{ env('OWNER_URL') }}" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                    Buka Dashboard Anda
                </a>
            @else
                <a href="/owner-panel" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                    Buka Dashboard Anda
                </a>
            @endif
        @else
            <a href="{{ route('landing.register') }}" class="bg-yellow-400 text-blue-900 px-8 py-3 rounded-lg font-semibold hover:bg-yellow-300 transition duration-300">
                Mulai Gratis Sekarang
            </a>
        @endauth
    </div>
</section>
@endsection