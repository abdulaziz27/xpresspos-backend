<?php

namespace App\Services\Ai\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiClient implements AiClientInterface
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('ai.api_key', '');
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    }

    public function ask(string $prompt): string
    {
        if (empty($this->apiKey)) {
            Log::warning('Gemini API key is not configured');
            return "Maaf, API key untuk Gemini belum dikonfigurasi. Silakan hubungi administrator.";
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $this->apiKey,
                ])
                ->post($this->apiUrl, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                $errorMessage = $response->json('error.message', 'Unknown error occurred');
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $response->body(),
                ]);

                return "Maaf, terjadi kesalahan saat menghubungi AI: " . $errorMessage;
            }

            $responseData = $response->json();

            // Extract text from Gemini response
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($responseData['candidates'][0]['content']['parts'][0]['text']);
            }

            Log::warning('Unexpected Gemini API response structure', [
                'response' => $responseData,
            ]);

            return "Maaf, format respons dari AI tidak sesuai. Silakan coba lagi.";
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Gemini API connection error', [
                'error' => $e->getMessage(),
            ]);

            return "Maaf, tidak dapat terhubung ke layanan AI. Silakan coba lagi nanti.";
        } catch (\Exception $e) {
            Log::error('Gemini API unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return "Maaf, terjadi kesalahan tidak terduga: " . $e->getMessage();
        }
    }
}

