<x-filament-widgets::widget>
    <x-filament::section>
        @php($viewData = $this->getViewData())
        @php($recommendations = $viewData['recommendations'] ?? [])

        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-5 h-5 text-yellow-500" style="width: 20px !important; height: 20px !important;" />
                Business Recommendations
            </div>
                @if(!empty($viewData['context']))
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $viewData['context'] }}
                    </p>
                @endif
        </x-slot>

        <div class="space-y-4">
            @forelse($recommendations as $recommendation)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0" style="width: 24px; height: 24px; min-width: 24px; max-width: 24px;">
                            @switch($recommendation['type'])
                                @case('low_margin')
                                    <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-red-500" style="width: 24px !important; height: 24px !important;" />
                                    @break
                                @case('slow_moving')
                                    <x-heroicon-o-clock class="w-6 h-6 text-yellow-500" style="width: 24px !important; height: 24px !important;" />
                                    @break
                                @case('popular_variants')
                                    <x-heroicon-o-star class="w-6 h-6 text-green-500" style="width: 24px !important; height: 24px !important;" />
                                    @break
                                @default
                                    <x-heroicon-o-information-circle class="w-6 h-6 text-blue-500" style="width: 24px !important; height: 24px !important;" />
                            @endswitch
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $recommendation['title'] }}
                            </h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $recommendation['message'] }}
                            </p>
                            
                            @if(isset($recommendation['data']) && count($recommendation['data']) > 0)
                                <div class="mt-3">
                                    <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Affected Items:
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach(array_slice($recommendation['data'], 0, 5) as $item)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                                @if(isset($item['product_name']))
                                                    {{ $item['product_name'] }}
                                                @elseif(isset($item['name']))
                                                    {{ $item['name'] }}
                                                @else
                                                    {{ $item }}
                                                @endif
                                            </span>
                                        @endforeach
                                        @if(count($recommendation['data']) > 5)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-200">
                                                +{{ count($recommendation['data']) - 5 }} more
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md">
                                <div class="flex items-start gap-2">
                                    <x-heroicon-o-arrow-right class="w-4 h-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" style="width: 16px !important; height: 16px !important;" />
                                    <p class="text-sm text-blue-800 dark:text-blue-200">
                                        <strong>Action:</strong> {{ $recommendation['action'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-green-500 mx-auto mb-3 flex-shrink-0" style="width: 48px !important; height: 48px !important;" />
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        All Good!
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        No immediate recommendations at this time. Your business is running smoothly.
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>