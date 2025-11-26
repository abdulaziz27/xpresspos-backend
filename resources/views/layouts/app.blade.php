<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo/logo-4-(ori).svg') }}">

    <title>@yield('title', 'POS Xpress')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap">
    @unless(app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">
    <header class="bg-white border-b border-slate-200">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ route('landing') }}" class="text-lg font-semibold">POS Xpress</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('company') }}" class="hover:text-indigo-600">Company</a>
                <a href="{{ config('app.owner_url', url('/owner')) }}" class="hover:text-indigo-600">Owner Dashboard</a>
                <a href="/admin" class="hover:text-indigo-600">Admin Panel</a>
                <a href="{{ route('login') }}" class="rounded border border-indigo-500 px-3 py-1 text-indigo-600 hover:bg-indigo-50">Login</a>
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-6xl px-6 py-12">
        @yield('content')
    </main>
    <footer class="bg-white border-t border-slate-200">
        <div class="mx-auto max-w-6xl px-6 py-6 text-sm text-slate-500">
            &copy; {{ now()->format('Y') }} POS Xpress. All rights reserved.
        </div>
    </footer>
</body>
</html>
