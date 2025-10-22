<?php
declare(strict_types=1);

namespace Aiassi\Lib;

final class Utils
{
    public static function slugify(string $text): string
    {
        $text = preg_replace('~[\p{Zs}]+~u', '-', trim($text));
        $text = strtolower((string) $text);
        $text = preg_replace('~[^a-z0-9\-]+~', '', $text) ?? '';
        $text = trim((string) $text, '-');
        return $text !== '' ? $text : 'file';
    }

    public static function readTextFromFile(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['txt','md','csv','log'], true)) {
            $raw = (string) @file_get_contents($path);
            return $raw !== '' ? $raw : '';
        }
        if ($ext === 'pdf') {
            $cmd = 'pdftotext -layout '.escapeshellarg($path).' -';
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') return (string) $out;
            $raw = (string) @file_get_contents($path);
            return $raw !== '' ? $raw : '';
        }
        if (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff'], true)) {
            $cmd = 'tesseract '.escapeshellarg($path).' stdout 2>NUL';
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') return (string) $out;
            return '';
        }
        $raw = (string) @file_get_contents($path);
        return $raw !== '' ? $raw : '';
    }

    /** @return list<string> */
    public static function splitIntoChunks(string $text, int $chunkSize, int $overlap): array
    {
        $normalized = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $tokens = preg_split('/(\s+)/u', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$normalized];
        $chunks = [];
        $current = '';
        foreach ($tokens as $token) {
            if (mb_strlen($current . $token, 'UTF-8') >= $chunkSize) {
                $chunks[] = trim($current);
                if ($overlap > 0 && $current !== '') {
                    $tail = mb_substr($current, max(0, mb_strlen($current, 'UTF-8') - $overlap), null, 'UTF-8');
                    $current = (string) $tail;
                } else {
                    $current = '';
                }
            }
            $current .= $token;
        }
        if (trim($current) !== '') { $chunks[] = trim($current); }
        return $chunks;
    }

    /** @return array<string,int> */
    public static function vectorize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/', trim($text)) ?: [];
        $freqs = [];
        foreach ($parts as $p) { if ($p==='') continue; $freqs[$p] = ($freqs[$p] ?? 0) + 1; }
        return $freqs;
    }

    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0; $magA = 0.0; $magB = 0.0;
        foreach ($a as $t=>$v) { $magA += $v*$v; if (isset($b[$t])) { $dot += $v*(int)$b[$t]; } }
        foreach ($b as $v) { $magB += $v*$v; }
        if ($magA === 0.0 || $magB === 0.0) return 0.0;
        return $dot / (sqrt($magA) * sqrt($magB));
    }
}


