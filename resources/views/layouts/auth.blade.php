<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'XpressPOS')</title>
    <meta name="description" content="@yield('description', 'Sistem POS terdepan untuk bisnis modern')">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo/logo-4-(ori).svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    
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
        
        /* Notification Popup Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .notification-popup {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.5s ease-out, fadeOut 0.5s ease-out 4.7s forwards;
            border-left: 5px solid;
            min-width: 300px;
        }
        
        .notification-popup.success {
            border-left-color: #10b981;
        }
        
        .notification-popup.error {
            border-left-color: #ef4444;
        }
        
        .notification-popup.warning {
            border-left-color: #f59e0b;
        }
        
        .notification-popup.info {
            border-left-color: #3b82f6;
        }
        
        .notification-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .notification-message {
            font-size: 13px;
            color: #6b7280;
        }
        
        .notification-close {
            flex-shrink: 0;
            cursor: pointer;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            transition: color 0.2s;
        }
        
        .notification-close:hover {
            color: #374151;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        @media (max-width: 640px) {
            .notification-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
            
            .notification-popup {
                min-width: auto;
            }
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
                    <a href="{{ config('app.url') }}" class="inline-block">
                        <img src="{{ asset('logo/logo-1-(ori-blue-ver).png') }}" alt="XpressPOS" class="h-18 w-auto mx-auto mb-3">
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
                <a href="{{ config('app.url') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors duration-300">
                    ← Kembali ke Beranda
                </a>
            </div>
        </div>
    </div>
    
    <!-- Notification Container -->
    <div id="notification-container" class="notification-container"></div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eyeIcon = document.getElementById(inputId + '-eye');
            const eyeOffIcon = document.getElementById(inputId + '-eye-off');
            
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }
        
        // Notification Popup Function
        function showNotification(message, type = 'success', duration = 5000) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification-popup ${type}`;
            
            const icons = {
                success: '<svg class="notification-icon text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                error: '<svg class="notification-icon text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                warning: '<svg class="notification-icon text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                info: '<svg class="notification-icon text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
            };
            
            notification.innerHTML = `
                ${icons[type] || icons.success}
                <div class="notification-content">
                    <div class="notification-message">${message}</div>
                </div>
                <button class="notification-close" onclick="this.parentElement.remove()">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            `;
            
            container.appendChild(notification);
            
            // Auto remove after duration
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, duration);
        }
        
        // Show notifications from session flash messages
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', function() {
                showNotification({!! json_encode(session('success')) !!}, 'success');
            });
        @endif
        
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', function() {
                showNotification({!! json_encode(session('error')) !!}, 'error');
            });
        @endif
        
        @if(session('warning'))
            document.addEventListener('DOMContentLoaded', function() {
                showNotification({!! json_encode(session('warning')) !!}, 'warning');
            });
        @endif
        
        @if(session('info'))
            document.addEventListener('DOMContentLoaded', function() {
                showNotification({!! json_encode(session('info')) !!}, 'info');
            });
        @endif
        
        // Show validation errors
        @if(isset($errors) && $errors->any())
            document.addEventListener('DOMContentLoaded', function() {
                @foreach($errors->all() as $error)
                    showNotification({!! json_encode($error) !!}, 'error');
                @endforeach
            });
        @endif
    </script>
</body>
</html>