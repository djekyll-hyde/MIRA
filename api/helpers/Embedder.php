<?php
require_once __DIR__ . '/../config.php';

class Embedder {
    public static function embed(string $text): array {
        $url  = GEMINI_BASE_URL . '/models/gemini-embedding-001:embedContent?key=' . GEMINI_API_KEY;
        $body = json_encode([
            'model'   => 'models/gemini-embedding-001',
            'content' => [
                'parts' => [['text' => $text]]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
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