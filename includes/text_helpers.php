<?php
/**
 * Shared text normalization helpers.
 */

if (!function_exists('normalizeDisplayText')) {
    function normalizeDisplayText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $decoded = trim($text);
        $prev = '';
        while ($decoded !== $prev) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $decoded;
    }
}

if (!function_exists('normalizeLiveTvChannel')) {
    function normalizeLiveTvChannel(?array $channel): ?array
    {
        if (!$channel) {
            return null;
        }
        foreach (['name', 'description', 'category', 'country'] as $field) {
            if (isset($channel[$field])) {
                $channel[$field] = normalizeDisplayText($channel[$field]);
            }
        }
        return $channel;
    }
}
