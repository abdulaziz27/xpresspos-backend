<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-3">
            <!-- Header -->
            <div class="flex items-center justify-between" style="margin-bottom: 8px;">
                <h3 class="text-gray-900 dark:text-white" style="font-weight: 600; font-size: 16px;">
                    Status Subscription
                </h3>
            </div>
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
                <div style="padding: 2px 12px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="font-size: 1.12rem; font-weight: 600;">{{ $activeSubscription->plan->name }}</div>
                        @if($isActive)
                            <span style="padding: 0 12px; background-color: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #86efac;">Active</span>
                        @else
                            <span style="padding: 4px 12px; background-color: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; border: 1px solid #fca5a5;">Expired</span>
                        @endif
                    </div>
                    <div style="font-size: 0.875rem; font-weight: 500;">
                        <span style="font-weight: 600;">Expired:</span> {{ $endsAt->format('d M Y') }}
                        @if($isActive)
                            <span style="color: #059669; margin-left: 4px;">({{ $displayRemaining() }})</span>
                        @else
                            <span style="color: #dc2626; margin-left: 4px;">({{ $displayElapsed() }})</span>
                        @endif
                    </div>
                </div>
            @else
                <div style="background-color: #f9fafb; padding: 16px; border-radius: 0.75rem; border: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px;">
                    <svg style="width: 20px; height: 20px; color: #9ca3af; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span style="font-size: 0.875rem; color: #374151; font-weight: 500;">Belum ada subscription aktif</span>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>