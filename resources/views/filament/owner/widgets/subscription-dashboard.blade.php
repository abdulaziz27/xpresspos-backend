<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Subscription & Billing
                </h3>
                <div class="flex space-x-2">
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.owner.resources.subscriptions.index') }}"
                        size="sm"
                        color="gray"
                    >
                        View All
                    </x-filament::button>
                </div>
            </div>

            <!-- Active Subscription Status -->
            @if($activeSubscription)
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-blue-900 dark:text-blue-100">
                            {{ $activeSubscription->plan->name }} Plan
                        </h4>
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            Active until {{ $activeSubscription->ends_at->format('M j, Y') }}
                            ({{ $activeSubscription->ends_at->diffInDays() }} days left)
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                            Rp {{ number_format($activeSubscription->amount, 0, ',', '.') }}
                        </div>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            per {{ $activeSubscription->billing_cycle }}
                        </div>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="text-center">
                    <x-heroicon-o-credit-card class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                    <h4 class="font-medium text-gray-900 dark:text-white">No Active Subscription</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                        Subscribe to a plan to access all features
                    </p>
                    <x-filament::button
                        tag="a"
                        href="{{ route('landing.home') }}"
                        size="sm"
                        color="primary"
                    >
                        View Plans
                    </x-filament::button>
                </div>
            </div>
            @endif

            <!-- Upcoming Renewal Alert -->
            @if($upcomingRenewal)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <div class="flex items-start space-x-3">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-yellow-600 mt-0.5" />
                    <div class="flex-1">
                        <h4 class="font-medium text-yellow-800 dark:text-yellow-200">
                            Renewal Due Soon
                        </h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">
                            Your {{ $upcomingRenewal->plan->name }} subscription renews in {{ $upcomingRenewal->ends_at->diffInDays() }} days
                            for Rp {{ number_format($upcomingRenewal->amount, 0, ',', '.') }}
                        </p>
                    </div>
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.owner.resources.subscriptions.view', $upcomingRenewal) }}"
                        size="sm"
                        color="warning"
                    >
                        Manage
                    </x-filament::button>
                </div>
            </div>
            @endif

            <!-- Pending Payments Alert -->
            @if($pendingPayments->count() > 0)
            <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                <div class="flex items-start space-x-3">
                    <x-heroicon-s-clock class="w-5 h-5 text-orange-600 mt-0.5" />
                    <div class="flex-1">
                        <h4 class="font-medium text-orange-800 dark:text-orange-200">
                            Pending Payments
                        </h4>
                        <p class="text-sm text-orange-700 dark:text-orange-300">
                            You have {{ $pendingPayments->count() }} pending payment(s) that need attention
                        </p>
                    </div>
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.owner.resources.subscription-payments.index') }}?tableFilters[status][value]=pending"
                        size="sm"
                        color="warning"
                    >
                        View
                    </x-filament::button>
                </div>
            </div>
            @endif

            <!-- Recent Payments -->
            @if($recentPayments->count() > 0)
            <div>
                <h4 class="font-medium text-gray-900 dark:text-white mb-3">Recent Payments</h4>
                <div class="space-y-3">
                    @foreach($recentPayments as $payment)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                @if($payment->isPaid())
                                    <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                                @elseif($payment->isPending())
                                    <x-heroicon-s-clock class="w-5 h-5 text-yellow-500" />
                                @elseif($payment->hasFailed())
                                    <x-heroicon-s-x-circle class="w-5 h-5 text-red-500" />
                                @else
                                    <x-heroicon-s-minus-circle class="w-5 h-5 text-gray-500" />
                                @endif
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">
                                    Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $payment->subscription?->plan?->name ?? 'Subscription' }} • 
                                    {{ $payment->created_at->format('M j, Y') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <x-filament::badge 
                                :color="match($payment->status) {
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'failed' => 'danger',
                                    'expired' => 'secondary',
                                    default => 'secondary'
                                }"
                            >
                                {{ ucfirst($payment->status) }}
                            </x-filament::badge>
                            @if($payment->paid_at)
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $payment->paid_at->format('g:i A') }}
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div class="mt-3 text-center">
                    <x-filament::button
                        tag="a"
                        href="{{ route('filament.owner.resources.subscription-payments.index') }}"
                        size="sm"
                        color="gray"
                        outlined
                    >
                        View All Payments
                    </x-filament::button>
                </div>
            </div>
            @else
            <div class="text-center py-6">
                <x-heroicon-o-banknotes class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No payment history yet</p>
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>