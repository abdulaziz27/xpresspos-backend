<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-4">
            <!-- Subscription Status Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $subscription->plan->name }} Subscription
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ ucfirst($subscription->billing_cycle) }} billing cycle
                    </p>
                </div>
                <div class="text-right">
                    <x-filament::badge 
                        :color="match($subscription->status) {
                            'active' => 'success',
                            'suspended' => 'warning', 
                            'cancelled' => 'danger',
                            'expired' => 'secondary',
                            default => 'secondary'
                        }"
                    >
                        {{ ucfirst($subscription->status) }}
                    </x-filament::badge>
                </div>
            </div>

            <!-- Subscription Timeline -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Started</div>
                    <div class="font-semibold text-gray-900 dark:text-white">
                        {{ $subscription->starts_at->format('M j, Y') }}
                    </div>
                </div>
                
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @if($isExpired)
                            Expired
                        @else
                            Expires
                        @endif
                    </div>
                    <div class="font-semibold {{ $isExpired ? 'text-red-600' : ($isExpiringSoon ? 'text-yellow-600' : 'text-gray-900 dark:text-white') }}">
                        {{ $subscription->ends_at->format('M j, Y') }}
                    </div>
                </div>
                
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @if($isExpired)
                            Expired
                        @else
                            Days Left
                        @endif
                    </div>
                    <div class="font-semibold {{ $isExpired ? 'text-red-600' : ($isExpiringSoon ? 'text-yellow-600' : 'text-gray-900 dark:text-white') }}">
                        @if($isExpired)
                            {{ abs($daysUntilRenewal) }} days ago
                        @else
                            {{ $daysUntilRenewal }} days
                        @endif
                    </div>
                </div>
            </div>

            <!-- Renewal Information -->
            @if($subscription->status === 'active' && !$isExpired)
            <div class="p-4 {{ $isExpiringSoon ? 'bg-yellow-50 border border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800' : 'bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800' }} rounded-lg">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        @if($isExpiringSoon)
                            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-yellow-600" />
                        @else
                            <x-heroicon-s-information-circle class="w-5 h-5 text-blue-600" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium {{ $isExpiringSoon ? 'text-yellow-800 dark:text-yellow-200' : 'text-blue-800 dark:text-blue-200' }}">
                            @if($isExpiringSoon)
                                Renewal Required Soon
                            @else
                                Next Renewal
                            @endif
                        </h4>
                        <p class="text-sm {{ $isExpiringSoon ? 'text-yellow-700 dark:text-yellow-300' : 'text-blue-700 dark:text-blue-300' }}">
                            Your subscription will renew on {{ $subscription->ends_at->format('M j, Y') }} for 
                            <strong>Rp {{ number_format($nextPaymentAmount, 0, ',', '.') }}</strong>
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Suspended Status -->
            @if($subscription->status === 'suspended')
            <div class="p-4 bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800 rounded-lg">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <x-heroicon-s-x-circle class="w-5 h-5 text-red-600" />
                    </div>
                    <div class="flex-1">
                        <h4 class="font-medium text-red-800 dark:text-red-200">
                            Subscription Suspended
                        </h4>
                        <p class="text-sm text-red-700 dark:text-red-300">
                            Your subscription has been suspended due to payment issues. Please resolve the payment to reactivate your service.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Latest Payment Info -->
            @if($latestPayment)
            <div class="border-t pt-4">
                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Latest Payment</h4>
                <div class="flex items-center justify-between text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Amount:</span>
                        <span class="font-medium text-gray-900 dark:text-white ml-1">
                            Rp {{ number_format($latestPayment->amount, 0, ',', '.') }}
                        </span>
                    </div>
                    <div>
                        <x-filament::badge 
                            :color="match($latestPayment->status) {
                                'paid' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger', 
                                'expired' => 'secondary',
                                default => 'secondary'
                            }"
                        >
                            {{ ucfirst($latestPayment->status) }}
                        </x-filament::badge>
                    </div>
                </div>
                @if($latestPayment->paid_at)
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Paid on {{ $latestPayment->paid_at->format('M j, Y \a\t g:i A') }}
                </div>
                @endif
            </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>