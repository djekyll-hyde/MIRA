<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Smalot\PdfParser\Parser;

class PdfParser {
    public static function extract(string $filePath): string {
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($filePath);
            $text   = $pdf->getText();
            // Clean up whitespace
            $text   = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        } catch (Exception $e) {
            throw new Exception('Failed to parse PDF: ' . $e->getMessage());
        }
    }
}