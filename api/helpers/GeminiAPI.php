<?php
require_once __DIR__ . '/../config.php';

class GeminiAPI {

    // ── Clean text to safe UTF-8 ──────────────────────────────────────────
    private static function cleanText(string $text): string {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text);
        return trim($text);
    }

    // ── Generate text (chat / summarise) ──────────────────────────────────
    public static function generate(string $prompt, array $history = []): string {
        $url = GEMINI_BASE_URL . '/models/' . GEMINI_CHAT_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

        $prompt   = self::cleanText($prompt);
        $contents = [];

        foreach ($history as $msg) {
            $contents[] = [
                'role'  => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => self::cleanText($msg['content'])]]
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $prompt]]
        ];

        $body = json_encode([
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 1024,
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if (!$body || $body === 'false') {
            throw new Exception('Failed to encode request body: ' . json_last_error_msg());
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body)
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Gemini API error (' . $httpCode . '): ' . $response);
        }

        $data = json_decode($response, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ── Embed text ────────────────────────────────────────────────────────
    public static function embed(string $text): array {
        $text = self::cleanText($text);
        $url  = GEMINI_BASE_URL . '/models/gemini-embedding-001:embedContent?key=' . GEMINI_API_KEY;

        $body = json_encode([
            'model'   => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => $text]]]
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if (!$body || $body === 'false') {
            throw new Exception('Failed to encode embed request: ' . json_last_error_msg());
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body)
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Embedding API error: ' . $response);
        }

        $data = json_decode($response, true);
        return $data['embedding']['values'] ?? [];
    }
}