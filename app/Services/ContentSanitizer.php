<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

/**
 * Membersihkan HTML dari editor Tiptap sebelum disimpan. Semua tag dan atribut
 * di luar whitelist dibuang, termasuk <script>, on* handler, dan skema URL
 * berbahaya (javascript:, data:).
 */
class ContentSanitizer
{
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return (string) Purifier::clean($html, 'default');
    }

    /**
     * Ringkasan teks polos untuk kebutuhan meta description / excerpt otomatis.
     */
    public function toPlainText(?string $html, int $limit = 155): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $html)));

        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit).'…';
    }
}
