<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XpressPOS - AI-Powered POS System')</title>
    <meta name="description" content="@yield('description', 'Kelola toko, restoran, dan bisnis dengan AI. Tidak perlu keahlian akuntansi dan manajemen khusus.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50">
    {{-- Main Content --}}
    @yield('content')
    
    @livewireScripts
</body>
</html>
