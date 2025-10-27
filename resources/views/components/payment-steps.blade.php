@props(['currentStep' => 1])

@php
$steps = [
    1 => ['title' => 'Keranjang Anda', 'description' => 'Review paket yang dipilih'],
    2 => ['title' => 'Informasi Bisnis', 'description' => 'Alamat penagihan & data bisnis'],
    3 => ['title' => 'Pembayaran', 'description' => 'Pilih metode pembayaran'],
    4 => ['title' => 'Konfirmasi', 'description' => 'Status pembayaran']
];

$progressPercentage = ($currentStep / 4) * 100;
@endphp

<!-- Progress Steps -->
<div class="max-w-4xl mx-auto mb-12 animate-fade-in-up" style="animation-delay: 0.1s">
    <div class="bg-white rounded-2xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            @foreach($steps as $stepNumber => $step)
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
                                @elseif($currentStep == 3) Pilih metode
                                @elseif($currentStep == 4) Berhasil!
                                @else Aktif
                                @endif
                            @else Menunggu
                            @endif
                        </p>
                    </div>
                </div>

                @if($stepNumber < 4)
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
            @endforeach
        </div>

        <!-- Mobile Progress Bar -->
        <div class="mt-4 sm:hidden">
            <div class="flex justify-between text-xs text-gray-600 mb-2">
                <span>
                    @if($currentStep == 4) Selesai!
                    @else Langkah {{ $currentStep }} dari 4
                    @endif
                </span>
                <span>{{ number_format($progressPercentage, 0) }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="
                    @if($currentStep == 4) bg-green-500
                    @else bg-blue-600
                    @endif
                    h-2 rounded-full transition-all duration-500" 
                    style="width: {{ $progressPercentage }}%">
                </div>
            </div>
            <div class="text-center mt-2">
                <p class="text-sm font-medium 
                    @if($currentStep == 4) text-green-600
                    @else text-blue-600
                    @endif">
                    @if($currentStep == 4) Pembayaran Berhasil!
                    @else {{ $steps[$currentStep]['title'] }}
                    @endif
                </p>
            </div>
        </div>
    </div>
</div>