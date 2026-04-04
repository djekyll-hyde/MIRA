<?php
class Chunker {
    private static int $chunkSize   = 500; // words per chunk
    private static int $overlapSize = 50;  // words of overlap

    public static function chunk(string $text): array {
        $words  = explode(' ', $text);
        $total  = count($words);
        $chunks = [];
        $i      = 0;

        while ($i < $total) {
            $slice    = array_slice($words, $i, self::$chunkSize);
            $chunks[] = implode(' ', $slice);
            $i       += self::$chunkSize - self::$overlapSize;
        }

        // Remove empty or very short chunks
        return array_values(array_filter($chunks, fn($c) => str_word_count($c) > 20));
    }
}