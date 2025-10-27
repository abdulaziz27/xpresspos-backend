<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XpressPOS')</title>
    <meta name="description" content="@yield('description', 'Sistem POS terdepan untuk bisnis modern')">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .font-sf { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        
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
            animation-delay: 0s;
        }
        
        .gradient-orb-2 {
            width: 300px;
            height: 300px;
            background: linear-gradient(45deg, #ec4899, #10b981);
            bottom: -150px;
            right: -150px;
            animation-delay: -10s;
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
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="h-full font-sf antialiased">
    <div class="min-h-full hero-gradient">
        <!-- Gradient Orbs -->
        <div class="gradient-orb gradient-orb-1"></div>
        <div class="gradient-orb gradient-orb-2"></div>
        
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.02"%3E%3Ccircle cx="30" cy="30" r="1"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-40"></div>
        
        <div class="relative flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div class="sm:mx-auto sm:w-full sm:max-w-md">
                <!-- Logo -->
                <div class="text-center animate-fade-in-up">
                    <a href="{{ route('landing.main') }}" class="inline-block">
                        <h1 class="text-3xl font-bold text-blue-600">XpressPOS</h1>
                        <p class="text-sm text-gray-600 mt-1">Smart POS System</p>
                    </a>
                </div>
            </div>

            <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md animate-fade-in-up" style="animation-delay: 0.2s">
                <div class="glass-effect py-8 px-4 shadow-2xl sm:rounded-2xl sm:px-10">
                    @yield('content')
                </div>
            </div>
            
            <!-- Back to Home -->
            <div class="mt-8 text-center animate-fade-in-up" style="animation-delay: 0.4s">
                <a href="{{ route('landing.main') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors duration-300">
                    ← Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
</body>
</html>