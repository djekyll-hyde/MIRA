<?php
class Similarity {
    public static function cosine(array $a, array $b): float {
        $dot   = 0;
        $normA = 0;
        $normB = 0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0 || $normB == 0) return 0;
        return $dot / (sqrt($normA) * sqrt($normB));
    }

    public static function topK(array $queryEmbedding, array $chunks, int $k = 5): array {
        $scored = [];

        foreach ($chunks as $chunk) {
            $embedding = json_decode($chunk['embedding_json'], true);
            if (!$embedding) continue;
            $scored[] = [
                'chunk' => $chunk,
                'score' => self::cosine($queryEmbedding, $embedding),
            ];
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top k chunks only
        return array_slice(array_column($scored, 'chunk'), 0, $k);
    }
}