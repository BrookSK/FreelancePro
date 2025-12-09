<?php

namespace App\Services;

use App\Models\AdminConfig;
use App\Models\AILog;

/**
 * OpenAIService - Integração com OpenAI GPT
 */
class OpenAIService
{
    protected string $apiKey;
    protected string $model = 'gpt-3.5-turbo';
    protected string $apiUrl = 'https://api.openai.com/v1/chat/completions';
    protected int $maxTokens = 1200;
    protected int $timeout = 90;

    public function __construct()
    {
        $config = new AdminConfig();
        $this->apiKey = $config->get('openai_api_key', '');
        $configuredModel = $config->get('openai_model', '');
        if (!empty($configuredModel)) {
            $this->model = $configuredModel;
        }
        $configuredMax = (int) $config->get('openai_max_tokens', 0);
        if ($configuredMax > 0) {
            $this->maxTokens = $configuredMax;
        }
        $configuredTimeout = (int) $config->get('openai_timeout', 0);
        if ($configuredTimeout > 0) {
            $this->timeout = $configuredTimeout;
        }
    }

    public function generateContent(string $prompt, int $userId, string $action = 'generate', ?int $timeoutSeconds = null): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Chave da API OpenAI não configurada.');
        }

        $aiLog = new AILog();

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um assistente especializado em criar conteúdo corporativo de alta qualidade em português brasileiro.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => 0.7,
        ];

        $timeoutToUse = $timeoutSeconds !== null ? max(5, min($timeoutSeconds, $this->timeout)) : $this->timeout;
        $connectTimeout = min(10, $timeoutToUse);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => $timeoutToUse,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $aiLog->logError($userId, $action, $prompt, "cURL error: {$error}");
            throw new \Exception("Erro de conexão: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $result['error']['message'] ?? 'Erro desconhecido';
            $aiLog->logError($userId, $action, $prompt, $errorMsg);
            throw new \Exception("Erro da API: {$errorMsg}");
        }

        $content = $result['choices'][0]['message']['content'] ?? '';
        $tokens = $result['usage']['total_tokens'] ?? 0;

        $aiLog->logSuccess($userId, $action, $prompt, $content, $tokens, $this->model);

        return $content;
    }

    public function transcribeAudio(string $audioPath, int $userId): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Chave da API OpenAI não configurada.');
        }

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        
        $postFields = [
            'file' => new \CURLFile($audioPath),
            'model' => 'whisper-1',
            'language' => 'pt',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new \Exception('Erro ao transcrever áudio: ' . ($result['error']['message'] ?? 'Erro desconhecido'));
        }

        return $result['text'] ?? '';
    }
}
