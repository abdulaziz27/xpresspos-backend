<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XpressPOS - Local Development</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-4xl mx-auto p-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">XpressPOS</h1>
                <p class="text-xl text-gray-600">Local Development Navigation</p>
                <p class="text-sm text-gray-500 mt-2">Choose which part of the application you want to access</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Landing Page -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Landing Page</h3>
                            <p class="text-sm text-gray-600">Marketing & Registration</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">Homepage, pricing, features, dan form registrasi untuk user baru.</p>
                    <a href="/landing" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-300">
                        Open Landing Page
                    </a>
                </div>

                <!-- API Documentation -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">API Documentation</h3>
                            <p class="text-sm text-gray-600">REST API Endpoints</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">API endpoints untuk mobile app dan integrasi eksternal.</p>
                    <div class="space-y-2">
                        <a href="/api" class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition duration-300 mr-2">
                            API Info
                        </a>
                        <a href="/api/docs" class="inline-block bg-green-100 text-green-700 px-4 py-2 rounded-md hover:bg-green-200 transition duration-300">
                            Documentation
                        </a>
                    </div>
                </div>

                <!-- Owner Dashboard -->
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Owner Dashboard</h3>
                            <p class="text-sm text-gray-600">Store Management</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">Filament panel untuk pemilik toko mengelola produk, pesanan, dan laporan.</p>
                    <a href="/owner-panel" class="inline-block bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition duration-300">
                        Open Owner Panel
                    </a>
                </div>

                <!-- Admin Panel -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-600 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Admin Panel</h3>
                            <p class="text-sm text-gray-600">System Administration</p>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-4">Panel admin untuk mengelola sistem, user, dan konfigurasi global.</p>
                    <a href="/admin-panel" class="inline-block bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 transition duration-300">
                        Open Admin Panel
                    </a>
                </div>
            </div>

            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="flex items-start">
                    <div class="w-6 h-6 text-yellow-600 mt-0.5">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-semibold text-yellow-800">Development Mode</h4>
                        <p class="text-sm text-yellow-700 mt-1">
                            Halaman ini hanya muncul di environment development. Di production, setiap domain akan langsung menampilkan konten yang sesuai.
                        </p>
                        <div class="mt-2 text-xs text-yellow-600">
                            <p><strong>Production URLs:</strong></p>
                            <p>• Landing: https://xpresspos.id</p>
                            <p>• API: https://api.xpresspos.id</p>
                            <p>• Owner: https://dashboard.xpresspos.id</p>
                            <p>• Admin: https://admin.xpresspos.id</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>