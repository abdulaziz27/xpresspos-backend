<x-filament-panels::page>
    <style>
        .ai-chat-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .ai-chat-container {
                grid-template-columns: 2fr 1fr;
            }
        }
        .ai-chat-box {
            height: 500px;
            overflow-y: auto;
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
        }
        .ai-message-bubble {
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.4;
            margin: 0;
        }
        .ai-message-user {
            background: #10b981;
            color: white;
            border-top-right-radius: 0;
        }
        .ai-message-assistant {
            background: white;
            color: #111827;
            border: 1px solid #e5e7eb;
            border-top-left-radius: 0;
        }
        .ai-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            background: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .ai-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
        }
        .ai-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .ai-button {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            font-weight: 500;
            border: none;
        }
        .ai-button-primary {
            background: #10b981;
            color: white;
        }
        .ai-button-primary:hover {
            background: #059669;
        }
        .ai-button-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .ai-button-secondary:hover {
            background: #f9fafb;
        }
    </style>
    
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('message-added', () => {
                setTimeout(() => {
                    const chatBox = document.getElementById('chat-messages');
                    if (chatBox) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                }, 100);
            });
        });
    </script>
    
    <div style="padding: 1rem; max-width: 1400px; margin: 0 auto;">
        <div class="ai-chat-container">
            {{-- LEFT: Chat --}}
            <div>
                {{-- Chat Messages --}}
                <div class="ai-card" style="margin-bottom: 1rem;">
                    <div style="padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb;">
                        <h3 style="font-weight: 600; font-size: 0.9rem; margin: 0; color: #111827;">Percakapan</h3>
                    </div>
                    <div style="padding: 1rem;">
                        <div class="ai-chat-box" id="chat-messages" wire:ignore.self>
                            @if(empty($this->messages) || count($this->messages) === 0)
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
                                    <div style="text-align: center;">
                                        <p style="color: #6b7280; font-size: 0.875rem;">Mulai percakapan dengan Asisten AI</p>
                                    </div>
                                </div>
                            @else
                                @foreach($this->messages as $index => $message)
                                    <div style="display: flex; margin-bottom: 0.75rem; {{ $message['role'] === 'user' ? 'justify-content: flex-end;' : 'justify-content: flex-start;' }}">
                                        <div style="max-width: 80%; display: flex; gap: 0.5rem; {{ $message['role'] === 'user' ? 'flex-direction: row-reverse;' : 'flex-direction: row;' }}">
                                            {{-- Avatar --}}
                                            <div style="width: 1.75rem; height: 1.75rem; border-radius: 50%; background: {{ $message['role'] === 'user' ? '#10b981' : '#9ca3af' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <span style="color: white; font-size: 0.7rem; font-weight: 600;">{{ $message['role'] === 'user' ? 'U' : 'AI' }}</span>
                                            </div>
                                            {{-- Bubble --}}
                                            <div style="display: flex; flex-direction: column; {{ $message['role'] === 'user' ? 'align-items: flex-end;' : 'align-items: flex-start;' }}; flex: 1;">
                                                <div class="ai-message-bubble {{ $message['role'] === 'user' ? 'ai-message-user' : 'ai-message-assistant' }}" style="margin: 0;">
                                                    <div style="margin: 0; padding: 0;">{{ $message['content'] }}</div>
                                                </div>
                                                <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.2rem; padding: 0 0.4rem;">
                                                    {{ \Carbon\Carbon::parse($message['created_at'])->format('d M Y, H:i') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Input Form --}}
                <div class="ai-card">
                    <div style="padding: 1rem;">
                        <form wire:submit.prevent="sendQuestion" style="display: flex; flex-direction: column; gap: 0.75rem;" wire:key="question-form">
                            <div>
                                <label for="question" style="display: block; font-size: 0.8rem; font-weight: 500; margin-bottom: 0.4rem; color: #111827;">
                                    Pertanyaan Anda
                                </label>
                                <textarea
                                    id="question"
                                    wire:model="question"
                                    rows="3"
                                    class="ai-input"
                                    style="resize: vertical;"
                                    placeholder="Ketik pertanyaan Anda di sini..."
                                ></textarea>
                                @error('question')
                                    <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #dc2626;">{{ $message }}</p>
                                @enderror
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                                <button
                                    type="button"
                                    wire:click="clearQuestion"
                                    wire:loading.attr="disabled"
                                    class="ai-button ai-button-secondary"
                                >
                                    Bersihkan
                                </button>
                                <button
                                    type="submit"
                                    wire:loading.attr="disabled"
                                    class="ai-button ai-button-primary"
                                >
                                    <span wire:loading.remove wire:target="sendQuestion">Kirim</span>
                                    <span wire:loading wire:target="sendQuestion">Mengirim...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Filters --}}
            <div>
                <div class="ai-card" style="margin-bottom: 1rem;">
                    <div style="padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb;">
                        <h3 style="font-weight: 600; font-size: 0.9rem; margin: 0; color: #111827;">Filter Data</h3>
                    </div>
                    <div style="padding: 1rem;">
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            {{-- Store Select --}}
                            <div>
                                <label for="storeId" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #111827;">
                                    Pilih Toko
                                </label>
                                <select
                                    id="storeId"
                                    wire:model.live="storeId"
                                    class="ai-input"
                                >
                                    <option value="">Semua Toko</option>
                                    @foreach($this->getStoreOptions() as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Date Preset --}}
                            <div>
                                <label for="dateRangePreset" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #111827;">
                                    Rentang Waktu
                                </label>
                                <select
                                    id="dateRangePreset"
                                    wire:model.live="dateRangePreset"
                                    class="ai-input"
                                >
                                    @foreach($this->getDateRangePresetOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($this->dateRangePreset === 'custom')
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <div>
                                        <label for="customFrom" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #111827;">
                                            Dari Tanggal
                                        </label>
                                        <input
                                            type="date"
                                            id="customFrom"
                                            wire:model="customFrom"
                                            class="ai-input"
                                        />
                                    </div>
                                    <div>
                                        <label for="customTo" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #111827;">
                                            Sampai Tanggal
                                        </label>
                                        <input
                                            type="date"
                                            id="customTo"
                                            wire:model="customTo"
                                            class="ai-input"
                                        />
                                    </div>
                                </div>
                            @endif

                            {{-- Active Filters Info --}}
                            <div style="padding-top: 1rem; border-top: 1px solid #e5e7eb; font-size: 0.875rem;">
                                @php
                                    [$from, $to] = $this->resolveDateRange();
                                @endphp
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.25rem;">
                                    <span style="font-weight: 500; color: #4b5563;">Toko:</span>
                                    <span style="color: #111827;">
                                        @if($this->storeId)
                                            {{ \App\Models\Store::find($this->storeId)?->name ?? 'Tidak ditemukan' }}
                                        @else
                                            Semua Toko
                                        @endif
                                    </span>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <span style="font-weight: 500; color: #4b5563;">Periode:</span>
                                    <span style="color: #111827;">
                                        {{ $from->format('d M Y') }} - {{ $to->format('d M Y') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tips --}}
                <div style="border: 1px solid #10b981; border-radius: 0.5rem; background: #ecfdf5;">
                    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #10b981;">
                        <h3 style="font-weight: 600; font-size: 1rem; margin: 0; color: #065f46;">Tips Penggunaan</h3>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem; color: #065f46;">
                            <div style="display: flex; gap: 0.5rem;">
                                <span>✅</span>
                                <span>Tanyakan tentang penjualan, stok, atau COGS</span>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span>✅</span>
                                <span>Pilih toko dan periode untuk analisis spesifik</span>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span>✅</span>
                                <span>Pertanyaan dianalisis berdasarkan data terbaru</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
