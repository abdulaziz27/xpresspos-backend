<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XpressPOS - AI-Powered POS System')</title>
    <meta name="description" content="@yield('description', 'Kelola toko, restoran, dan bisnis dengan AI. Tidak perlu keahlian akuntansi dan manajemen khusus.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo/ori.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50">
    {{-- Modern Responsive Navbar --}}
    @include('components.navbar')
    
    {{-- Main Content with top padding to account for fixed navbar --}}
    <div class="pt-16">
        @yield('content')
    </div>
    
    @livewireScripts
</body>
</html>
