<?php

namespace App\Services\Ai\Clients;

use Illuminate\Support\Str;

class DummyAiClient implements AiClientInterface
{
    public function ask(string $prompt): string
    {
        if (!config('ai.enabled', false)) {
            return "AI belum dikonfigurasi.\n\nPrompt yang akan dikirim:\n---\n" . Str::limit($prompt, 2000);
        }

        // TODO: di masa depan, implementasikan panggilan ke Gemini / OpenAI di sini.
        // Untuk sementara, balikan pesan placeholder agar tidak error.
        return "Ini adalah jawaban simulasi dari Asisten AI. Integrasi ke LLM belum diaktifkan di environment ini.";
    }
}

