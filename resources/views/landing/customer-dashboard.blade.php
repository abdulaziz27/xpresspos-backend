@extends('layouts.xpresspos')

@section('title', 'Dashboard Customer - XpressPOS')
@section('description', 'Dashboard untuk tracking subscription dan pembayaran XpressPOS')

@section('content')
<main class="overflow-hidden">
    <!-- Dashboard Section -->
    <section class="relative w-full hero-gradient">
        <!-- Content Container -->
        <div class="relative pt-24 pb-16">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Header -->
                <div class="text-center mb-16 animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl font-bold text-blue-600 mb-4">
                        Dashboard Customer
                    </h1>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Kelola subscription dan tracking pembayaran XpressPOS Anda
                    </p>
                    <p class="text-lg text-gray-500 mt-2">{{ $email }}</p>
                </div>

                @if($subscriptions->count() > 0)
                <!-- Subscriptions List -->
                <div class="space-y-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    @foreach($subscriptions as $subscription)
                    <div class="bg-white rounded-2xl shadow-xl p-8">
                        <!-- Subscription Header -->
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $subscription->business_name }}</h2>
                                <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                    <span>Paket: XpressPOS {{ ucfirst($subscription->plan_id) }}</span>
                                    <span>•</span>
                                    <span>Billing: {{ ucfirst($subscription->billing_cycle) }}</span>
                                    <span>•</span>
                                    <span>Dibuat: {{ $subscription->created_at->format('d M Y') }}</span>
                                </div>
                            </div>
                            <div class="mt-4 lg:mt-0">
                                @php
                                    $statusColors = [
                                        'pending_payment' => 'bg-yellow-100 text-yellow-800',
                                        'paid' => 'bg-green-100 text-green-800',
                                        'payment_failed' => 'bg-red-100 text-red-800',
                                        'active' => 'bg-blue-100 text-blue-800',
                                        'suspended' => 'bg-gray-100 text-gray-800'
                                    ];
                                    $statusColor = $statusColors[$subscription->status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="px-4 py-2 rounded-full text-sm font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                                </span>
                            </div>
                        </div>

                        <!-- Subscription Details -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                            <!-- Payment Info -->
                            <div class="bg-blue-50 rounded-xl p-6">
                                <h3 class="font-semibold text-blue-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                    Informasi Pembayaran
                                </h3>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-blue-700">Total:</span>
                                        <span class="font-semibold text-blue-900">Rp {{ number_format($subscription->payment_amount ?? 0, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-blue-700">Status:</span>
                                        <span class="font-semibold text-blue-900">{{ ucfirst(str_replace('_', ' ', $subscription->payment_status ?? 'pending')) }}</span>
                                    </div>
                                    @if($subscription->paid_at)
                                    <div class="flex justify-between">
                                        <span class="text-blue-700">Dibayar:</span>
                                        <span class="font-semibold text-blue-900">{{ $subscription->paid_at->format('d M Y H:i') }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Business Info -->
                            <div class="bg-green-50 rounded-xl p-6">
                                <h3 class="font-semibold text-green-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2M7 7h10M7 11h4m6 0h4M7 15h10"></path>
                                    </svg>
                                    Informasi Bisnis
                                </h3>
                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="text-green-700">Nama:</span>
                                        <span class="font-semibold text-green-900 block">{{ $subscription->business_name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-green-700">Jenis:</span>
                                        <span class="font-semibold text-green-900 block">{{ ucfirst($subscription->business_type ?? 'N/A') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-green-700">Kontak:</span>
                                        <span class="font-semibold text-green-900 block">{{ $subscription->phone ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="bg-purple-50 rounded-xl p-6">
                                <h3 class="font-semibold text-purple-900 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4"></path>
                                    </svg>
                                    Aksi
                                </h3>
                                <div class="space-y-3">
                                    @if($subscription->status === 'paid' || $subscription->status === 'active')
                                    <a href="{{ config('domains.owner', 'http://owner.xpresspos.id') }}" 
                                       class="block w-full text-center bg-blue-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                                        Buka Dashboard
                                    </a>
                                    @endif
                                    
                                    @if($subscription->status === 'pending_payment' || $subscription->status === 'payment_failed')
                                    <a href="{{ route('landing.payment') }}?subscription_id={{ $subscription->id }}&invoice_id={{ $subscription->xendit_invoice_id }}" 
                                       class="block w-full text-center bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors">
                                        Bayar Sekarang
                                    </a>
                                    @endif
                                    
                                    <button onclick="showPaymentHistory({{ $subscription->id }})" 
                                            class="block w-full text-center bg-gray-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-gray-700 transition-colors">
                                        Lihat History
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Payment History (Hidden by default) -->
                        <div id="payment-history-{{ $subscription->id }}" class="hidden border-t border-gray-200 pt-6">
                            <h3 class="font-semibold text-gray-900 mb-4">History Pembayaran</h3>
                            <div class="payment-history-content">
                                <div class="text-center py-4">
                                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto"></div>
                                    <p class="text-gray-600 mt-2">Memuat history pembayaran...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <!-- No Subscriptions -->
                <div class="text-center animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="bg-white rounded-2xl shadow-xl p-12">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Belum Ada Subscription</h2>
                        <p class="text-gray-600 mb-8">Anda belum memiliki subscription XpressPOS. Mulai berlangganan sekarang!</p>
                        <a href="{{ route('landing.pricing') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-300">
                            Pilih Paket Subscription
                        </a>
                    </div>
                </div>
                @endif

                <!-- Back to Home -->
                <div class="text-center mt-12">
                    <a href="{{ route('landing.home') }}" 
                       class="text-blue-600 hover:text-blue-700 font-medium transition-colors duration-300 flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali ke Beranda
                    </a>
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

<script>
async function showPaymentHistory(subscriptionId) {
    const historyDiv = document.getElementById(`payment-history-${subscriptionId}`);
    const contentDiv = historyDiv.querySelector('.payment-history-content');
    
    // Toggle visibility
    if (historyDiv.classList.contains('hidden')) {
        historyDiv.classList.remove('hidden');
        
        try {
            const response = await fetch(`/api/v1/subscription-payments/history?landing_subscription_id=${subscriptionId}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                let historyHtml = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr>';
                historyHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>';
                historyHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>';
                historyHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>';
                historyHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>';
                historyHtml += '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>';
                historyHtml += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';
                
                result.data.forEach(payment => {
                    const statusColors = {
                        'paid': 'bg-green-100 text-green-800',
                        'pending': 'bg-yellow-100 text-yellow-800',
                        'failed': 'bg-red-100 text-red-800',
                        'expired': 'bg-gray-100 text-gray-800'
                    };
                    const statusColor = statusColors[payment.status] || 'bg-gray-100 text-gray-800';
                    
                    historyHtml += '<tr>';
                    historyHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${payment.id}</td>`;
                    historyHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp ${new Intl.NumberFormat('id-ID').format(payment.amount)}</td>`;
                    historyHtml += `<td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs font-semibold rounded-full ${statusColor}">${payment.status}</span></td>`;
                    historyHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${payment.payment_method || 'N/A'}</td>`;
                    historyHtml += `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${new Date(payment.created_at).toLocaleDateString('id-ID')}</td>`;
                    historyHtml += '</tr>';
                });
                
                historyHtml += '</tbody></table></div>';
                contentDiv.innerHTML = historyHtml;
            } else {
                contentDiv.innerHTML = '<p class="text-gray-600 text-center py-4">Belum ada history pembayaran</p>';
            }
        } catch (error) {
            console.error('Error loading payment history:', error);
            contentDiv.innerHTML = '<p class="text-red-600 text-center py-4">Gagal memuat history pembayaran</p>';
        }
    } else {
        historyDiv.classList.add('hidden');
    }
}
</script>
@endsection