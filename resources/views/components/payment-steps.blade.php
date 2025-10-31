@props(['currentStep' => 1])

@php
$steps = [
    1 => ['title' => 'Pilih Paket', 'description' => 'Review paket yang dipilih'],
    2 => ['title' => 'Informasi Bisnis', 'description' => 'Data bisnis & kontak'],
    3 => ['title' => 'Pembayaran', 'description' => 'Proses pembayaran'],
    4 => ['title' => 'Selesai', 'description' => 'Pembayaran berhasil']
];

$maxSteps = 4;
$progressPercentage = ($currentStep / $maxSteps) * 100;
@endphp

<!-- Progress Steps -->
<div class="max-w-4xl mx-auto mb-12 animate-fade-in-up" style="animation-delay: 0.1s">
    <div class="bg-white rounded-2xl shadow-lg p-6">
        @if($currentStep == 4)
            <!-- Success State - Show all steps completed -->
            <div class="text-center">
                <div class="flex items-center justify-center space-x-4 mb-4">
                    @for($i = 1; $i <= 3; $i++)
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-10 h-10 bg-green-500 text-white rounded-full font-semibold text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            @if($i < 3)
                                <div class="w-16 h-0.5 bg-green-500 mx-2"></div>
                            @endif
                        </div>
                    @endfor
                </div>
                <p class="text-lg font-semibold text-green-600">✅ Semua langkah selesai!</p>
                <p class="text-sm text-gray-600">Pembayaran berhasil dan akun sudah aktif</p>
            </div>
        @else
            <!-- Normal Progress Steps -->
            <div class="flex items-center justify-between">
                @foreach($steps as $stepNumber => $step)
                    @if($stepNumber <= 3)
                        <!-- Step {{ $stepNumber }} -->
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-10 h-10 
                                @if($stepNumber < $currentStep) bg-green-500 text-white
                                @elseif($stepNumber == $currentStep) bg-blue-600 text-white
                                @else bg-gray-300 text-gray-600
                                @endif
                                rounded-full font-semibold text-sm">
                                @if($stepNumber < $currentStep)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                @else
                                    {{ $stepNumber }}
                                @endif
                            </div>
                            <div class="ml-3 hidden sm:block">
                                <p class="text-sm font-medium 
                                    @if($stepNumber < $currentStep) text-gray-900
                                    @elseif($stepNumber == $currentStep) text-blue-600
                                    @else text-gray-500
                                    @endif">
                                    {{ $step['title'] }}
                                </p>
                                <p class="text-xs 
                                    @if($stepNumber < $currentStep) text-gray-500
                                    @elseif($stepNumber == $currentStep) text-blue-500
                                    @else text-gray-400
                                    @endif">
                                    @if($stepNumber < $currentStep) Selesai
                                    @elseif($stepNumber == $currentStep) 
                                        @if($currentStep == 2) Sedang diisi
                                        @elseif($currentStep == 3) Memproses
                                        @else Aktif
                                        @endif
                                    @else Menunggu
                                    @endif
                                </p>
                            </div>
                        </div>

                        @if($stepNumber < 3)
                            <!-- Connector Line -->
                            <div class="flex-1 mx-4">
                                <div class="h-0.5 
                                    @if($stepNumber < $currentStep) bg-green-500
                                    @elseif($stepNumber == $currentStep) bg-blue-600
                                    @else bg-gray-300
                                    @endif">
                                </div>
                            </div>
                        @endif
                    @endif
                @endforeach
            </div>
        @endif

        <!-- Mobile Progress Bar -->
        <div class="mt-4 sm:hidden">
            @if($currentStep == 4)
                <div class="text-center">
                    <div class="w-full bg-green-200 rounded-full h-2 mb-2">
                        <div class="bg-green-600 h-2 rounded-full transition-all duration-500" style="width: 100%"></div>
                    </div>
                    <p class="text-sm font-medium text-green-600">✅ {{ $steps[$currentStep]['title'] }}</p>
                </div>
            @else
                <div class="flex justify-between text-xs text-gray-600 mb-2">
                    <span>Langkah {{ $currentStep }} dari 3</span>
                    <span>{{ number_format(($currentStep / 3) * 100, 0) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                        style="width: {{ ($currentStep / 3) * 100 }}%">
                    </div>
                </div>
                <div class="text-center mt-2">
                    <p class="text-sm font-medium text-blue-600">
                        {{ $steps[$currentStep]['title'] }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>