@extends('landing.layout')

@section('title', 'XpressPOS - Solusi POS Terdepan untuk Bisnis Anda')

@section('content')
<!-- Hero Section -->
<section class="relative bg-gradient-to-br from-indigo-900 via-purple-900 to-indigo-800 text-white overflow-hidden">
    <div class="absolute inset-0 bg-black opacity-20"></div>
    <div class="absolute inset-0">
        <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-r from-indigo-600/20 to-purple-600/20"></div>
        <div class="absolute top-20 left-20 w-72 h-72 bg-purple-500/10 rounded-full blur-3xl"></div>
        <div class="absolute bottom-20 right-20 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32">
        <div class="text-center">
            <h1 class="text-5xl md:text-7xl font-bold mb-8 leading-tight">
                Revolusi Bisnis Anda dengan <span class="bg-gradient-to-r from-yellow-400 to-orange-400 bg-clip-text text-transparent">XpressPOS</span>
            </h1>
            <p class="text-xl md:text-2xl mb-12 text-gray-200 max-w-4xl mx-auto leading-relaxed">
                Sistem Point of Sale yang powerful, mudah digunakan, dan terjangkau untuk semua jenis bisnis
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                @auth
                    @if(app()->environment('production') && env('OWNER_URL'))
                        <a href="{{ env('OWNER_URL') }}" class="bg-gradient-to-r from-yellow-400 to-orange-400 text-gray-900 px-10 py-4 rounded-xl font-bold text-lg hover:from-yellow-300 hover:to-orange-300 transform hover:scale-105 transition-all duration-300 shadow-xl">
                            Buka Dashboard
                        </a>
                    @else
                        <a href="/owner-panel" class="bg-gradient-to-r from-yellow-400 to-orange-400 text-gray-900 px-10 py-4 rounded-xl font-bold text-lg hover:from-yellow-300 hover:to-orange-300 transform hover:scale-105 transition-all duration-300 shadow-xl">
                            Buka Dashboard
                        </a>
                    @endif
                @else
                    <a href="{{ route('landing.register') }}" class="bg-gradient-to-r from-yellow-400 to-orange-400 text-gray-900 px-10 py-4 rounded-xl font-bold text-lg hover:from-yellow-300 hover:to-orange-300 transform hover:scale-105 transition-all duration-300 shadow-xl">
                        Mulai Gratis
                    </a>
                    <a href="{{ route('landing.login') }}" class="border-2 border-white/30 backdrop-blur-sm text-white px-10 py-4 rounded-xl font-semibold text-lg hover:bg-white/10 hover:border-white/50 transition-all duration-300">
                        Login
                    </a>
                @endauth
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-24 bg-gradient-to-b from-gray-50 to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-20">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                Mengapa Memilih XpressPOS?
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Fitur lengkap yang dirancang khusus untuk kebutuhan bisnis Indonesia
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="group bg-white p-10 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-indigo-200">
                <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold mb-4 text-gray-900">Super Cepat</h3>
                <p class="text-gray-600 leading-relaxed">Proses transaksi dalam hitungan detik dengan interface yang intuitif</p>
            </div>

            <div class="group bg-white p-10 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-indigo-200">
                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold mb-4 text-gray-900">Laporan Real-time</h3>
                <p class="text-gray-600 leading-relaxed">Monitor performa bisnis Anda dengan dashboard analytics yang komprehensif</p>
            </div>

            <div class="group bg-white p-10 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 border border-gray-100 hover:border-indigo-200">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold mb-4 text-gray-900">Keamanan Terjamin</h3>
                <p class="text-gray-600 leading-relaxed">Data bisnis Anda aman dengan enkripsi tingkat enterprise</p>
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