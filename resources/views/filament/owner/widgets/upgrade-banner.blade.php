<x-filament-widgets::widget>
    <x-filament::section>
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h3 class="text-xl font-bold mb-2">
                        🚀 Upgrade to Unlock Full Features
                    </h3>
                    <p class="text-blue-100 mb-4">
                        You're currently on the <strong>{{ $current_tier }}</strong> plan. 
                        Upgrade to access advanced analytics, multi-store management, and more!
                    </p>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-white/10 rounded-lg p-3">
                            <div class="text-sm text-blue-100">Products Used</div>
                            <div class="text-2xl font-bold">
                                {{ $usage['products']['current'] }} / {{ $usage['products']['limit'] }}
                            </div>
                            <div class="w-full bg-white/20 rounded-full h-2 mt-2">
                                <div class="bg-white rounded-full h-2" style="width: {{ $usage['products']['percentage'] }}%"></div>
                            </div>
                        </div>
                        
                        <div class="bg-white/10 rounded-lg p-3">
                            <div class="text-sm text-blue-100">Staff Members</div>
                            <div class="text-2xl font-bold">
                                {{ $usage['staff']['current'] }} / {{ $usage['staff']['limit'] }}
                            </div>
                            <div class="w-full bg-white/20 rounded-full h-2 mt-2">
                                <div class="bg-white rounded-full h-2" style="width: {{ $usage['staff']['percentage'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="ml-6">
                    <a href="{{ route('landing.pricing') }}" 
                       class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-blue-50 transition">
                        View Plans
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
