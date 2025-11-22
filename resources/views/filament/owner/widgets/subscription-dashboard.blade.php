<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-3">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Status Subscription
                </h3>
            </div>
            @if(!empty($filterContext))
                <p class="text-xs text-gray-500 dark:text-gray-300">
                    {{ $filterContext }}
                </p>
            @endif
            @if($activeSubscription)
                @php
                    $now = now();
                    $endsAt = $activeSubscription->ends_at;
                    $isActive = ($activeSubscription->status === 'active') && $endsAt->isFuture();

                    $secondsDiff = $now->diffInSeconds($endsAt, false);

                    $displayRemaining = function () use ($secondsDiff) {
                        if ($secondsDiff <= 0) return 'berakhir';
                        $minutes = intdiv($secondsDiff, 60);
                        if ($minutes < 60) {
                            return max(1, (int) $minutes) . ' menit lagi';
                        }
                        $hours = intdiv($secondsDiff, 3600);
                        if ($hours < 24) {
                            return (int) $hours . ' jam lagi';
                        }
                        $days = intdiv($secondsDiff, 86400);
                        return (int) $days . ' hari lagi';
                    };

                    $displayElapsed = function () use ($secondsDiff) {
                        $elapsed = abs($secondsDiff);
                        $minutes = intdiv($elapsed, 60);
                        if ($minutes < 60) {
                            return 'berakhir ' . max(1, (int) $minutes) . ' menit lalu';
                        }
                        $hours = intdiv($elapsed, 3600);
                        if ($hours < 24) {
                            return 'berakhir ' . (int) $hours . ' jam lalu';
                        }
                        $days = intdiv($elapsed, 86400);
                        return 'berakhir ' . (int) $days . ' hari lalu';
                    };
                @endphp
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800 flex flex-col gap-1">
                    <div class="flex items-baseline gap-2">
                        <div class="text-base font-bold text-blue-900 dark:text-blue-100">{{ $activeSubscription->plan->name }}</div>
                        @if($isActive)
                            <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-md">Active</span>
                        @else
                            <span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-md">Expired</span>
                        @endif
                    </div>
                    <div class="text-xs text-blue-700 dark:text-blue-300">
                        Expired: {{ $endsAt->format('d M Y') }}
                        @if($isActive)
                            ({{ $displayRemaining() }})
                        @else
                            ({{ $displayElapsed() }})
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-gray-50 dark:bg-gray-800 p-3 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center gap-2">
                    <x-heroicon-o-x-circle class="w-5 h-5 text-gray-400" />
                    <span class="text-gray-700 dark:text-gray-300 text-sm">Belum ada subscription aktif</span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>