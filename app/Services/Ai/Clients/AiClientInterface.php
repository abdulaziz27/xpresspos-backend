<?php

namespace App\Services\Ai\Clients;

interface AiClientInterface
{
    /**
     * Send a prompt to the AI service and get a response.
     *
     * @param string $prompt The final prompt containing instructions + context JSON + user question
     * @return string The AI response (plain text / markdown)
     */
    public function ask(string $prompt): string;
}

