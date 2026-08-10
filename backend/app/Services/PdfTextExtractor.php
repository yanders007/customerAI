<?php

namespace App\Services;

/**
 * PdfTextExtractor — Extraction de texte PDF robuste.
 *
 * Stratégie en 3 passes :
 *
 *  1. smalot/pdfparser (lib Composer) : le plus fiable pour les PDF
 *     texte modernes (Word → PDF, LibreOffice → PDF). Gère les
 *     encodages, les polices intégrées et les colonnes.
 *
 *  2. pdftotext (binaire système) : excellent pour les PDF complexes
 *     (colonnes multiples, tableaux). Disponible sur la plupart des
 *     serveurs Linux via poppler-utils.
 *
 *  3. Parseur maison (fallback pur PHP) : utilisé si les deux
 *     méthodes précédentes échouent ou retournent du texte vide.
 *     Décompresse les flux FlateDecode et lit les opérateurs Tj/TJ.
 *     Fonctionne sans aucune dépendance externe.
 *
 * Après extraction, le texte est nettoyé :
 *  - Caractères de contrôle et séquences UTF-8 invalides supprimés
 *  - Espaces et sauts de ligne normalisés
 *  - Lignes vides consécutives réduites (max 2)
 *
 * Les PDF scannés (images sans couche texte) ne sont PAS couverts
 * (nécessiterait de l'OCR — Tesseract).
 */
class PdfTextExtractor
{
    // ═══════════════════════════════════════════════════════════════════
    // Point d'entrée
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Extrait et nettoie tout le texte lisible d'un PDF.
     * Retourne une chaîne vide si le fichier est illisible ou scanné.
     */
    public function extract(string $filePath): string
    {
        if (!is_readable($filePath)) {
            return '';
        }

        // ── Passe 1 : smalot/pdfparser ───────────────────────────────
        $text = $this->extractWithPdfParser($filePath);

        // ── Passe 2 : pdftotext (poppler-utils) ─────────────────────
        if ($this->isEmpty($text)) {
            $text = $this->extractWithPdfToText($filePath);
        }

        // ── Passe 3 : parseur maison ──────────────────────────────────
        if ($this->isEmpty($text)) {
            $text = $this->extractWithManualParser($filePath);
        }

        return $this->isEmpty($text) ? '' : $this->cleanText($text);
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 1 — smalot/pdfparser
    // ═══════════════════════════════════════════════════════════════════

    private function extractWithPdfParser(string $filePath): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return '';
        }

        try {
            $parser   = new \Smalot\PdfParser\Parser();
            $pdf      = $parser->parseFile($filePath);
            $pages    = $pdf->getPages();
            $parts    = [];

            foreach ($pages as $page) {
                $pageText = $page->getText();
                if (trim($pageText) !== '') {
                    $parts[] = $pageText;
                }
            }

            return implode("\n\n", $parts);
        } catch (\Throwable) {
            return '';
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 2 — pdftotext (poppler-utils)
    // ═══════════════════════════════════════════════════════════════════

    private function extractWithPdfToText(string $filePath): string
    {
        // Vérifie que le binaire est disponible
        exec('which pdftotext 2>/dev/null', $out, $rc);
        if ($rc !== 0) {
            return '';
        }

        $escaped = escapeshellarg($filePath);
        // -layout : préserve la mise en page (colonnes, tableaux)
        // -enc UTF-8 : force l'encodage UTF-8 en sortie
        $cmd = "pdftotext -layout -enc UTF-8 {$escaped} - 2>/dev/null";

        $output = shell_exec($cmd);

        return $output ?? '';
    }

    // ═══════════════════════════════════════════════════════════════════
    // Passe 3 — Parseur maison (pur PHP, sans dépendance)
    // ═══════════════════════════════════════════════════════════════════

    private function extractWithManualParser(string $filePath): string
    {
        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }

        $streams = $this->findStreams($raw);

        // Construction de la table ToUnicode (polices embarquées)
        $cmap = [];
        foreach ($streams as $stream) {
            if (
                stripos($stream, 'beginbfchar')  !== false ||
                stripos($stream, 'beginbfrange') !== false
            ) {
                $cmap += $this->parseToUnicodeMap($stream);
            }
        }

        // Extraction du texte depuis les flux de contenu (Tj / TJ)
        $parts = [];
        foreach ($streams as $stream) {
            if (stripos($stream, 'Tj') !== false || stripos($stream, 'TJ') !== false) {
                $part = $this->extractTextFromContentStream($stream, $cmap);
                if (trim($part) !== '') {
                    $parts[] = $part;
                }
            }
        }

        $text = implode("\n", $parts);

        // Ultime fallback : chercher dans le fichier brut
        if (trim($text) === '') {
            $text = $this->extractTextFromContentStream($raw, $cmap);
        }

        return $text;
    }

    // ── Helpers parseur maison ────────────────────────────────────────

    private function findStreams(string $content): array
    {
        $streams = [];
        if (!preg_match_all('/<<(.*?)>>\s*stream\r?\n/s', $content, $dictMatches, PREG_OFFSET_CAPTURE)) {
            return $streams;
        }

        foreach ($dictMatches[0] as $i => $match) {
            $dict  = $dictMatches[1][$i][0];
            $start = $match[1] + strlen($match[0]);
            $end   = strpos($content, 'endstream', $start);
            if ($end === false) {
                continue;
            }

            $raw = rtrim(substr($content, $start, $end - $start), "\r\n");

            if (stripos($dict, 'FlateDecode') !== false) {
                $raw = $this->inflateStream($raw);
            }

            $streams[] = $raw;
        }

        return $streams;
    }

    private function inflateStream(string $raw): string
    {
        foreach ([
            fn ($d) => @gzuncompress($d),
            fn ($d) => @gzinflate($d),
            fn ($d) => @gzinflate(substr($d, 2)),
        ] as $try) {
            $out = $try($raw);
            if ($out !== false) {
                return $out;
            }
        }
        return $raw;
    }

    private function hexToUtf8(string $hex): string
    {
        if (strlen($hex) % 4 !== 0) {
            $hex = str_pad($hex, (int) ceil(strlen($hex) / 4) * 4, '0', STR_PAD_LEFT);
        }
        $bytes = @hex2bin($hex);
        if ($bytes === false) {
            return '';
        }
        $u = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
        return $u !== false ? $u : '';
    }

    private function parseToUnicodeMap(string $cmapText): array
    {
        $map = [];

        // Plages bfchar
        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmapText, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs);
                foreach ($pairs[1] as $i => $src) {
                    $map[strtoupper($src)] = $this->hexToUtf8($pairs[2][$i]);
                }
            }
        }

        // Plages bfrange
        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmapText, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges);
                foreach ($ranges[1] as $i => $lo) {
                    $loNum  = hexdec($lo);
                    $hiNum  = hexdec($ranges[2][$i]);
                    $dstNum = hexdec($ranges[3][$i]);
                    $span   = min($hiNum - $loNum, 2000);
                    for ($code = 0; $code <= $span; $code++) {
                        $key        = strtoupper(str_pad(dechex($loNum + $code), strlen($lo), '0', STR_PAD_LEFT));
                        $map[$key]  = $this->hexToUtf8(dechex($dstNum + $code));
                    }
                }
            }
        }

        return $map;
    }

    private function decodeLiteralString(string $s): string
    {
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(intval($m[1], 8)), $s) ?? $s;
        $s = preg_replace('/\\\\(\r\n|\r|\n)/', '', $s) ?? $s;
        $s = str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(',   ')',   '\\',   "\n",  "\r",  "\t"],
            $s
        );
        $u = @iconv('CP1252', 'UTF-8//IGNORE', $s);
        return $u !== false ? $u : $s;
    }

    private function decodeHexRun(string $hex, array $cmap): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? '';
        $out = '';
        $len = strlen($hex);
        for ($i = 0; $i + 4 <= $len; $i += 4) {
            $code = strtoupper(substr($hex, $i, 4));
            $out .= $cmap[$code] ?? $this->hexToUtf8($code);
        }
        return $out;
    }

    private function decodePiece(string $piece, array $cmap): string
    {
        if ($piece[0] === '<') {
            return $this->decodeHexRun(trim($piece, '<>'), $cmap);
        }
        return $this->decodeLiteralString(substr($piece, 1, -1));
    }

    private function extractTextFromContentStream(string $content, array $cmap): string
    {
        $out = '';
        preg_match_all(
            '/\((?:\\\\.|[^()\\\\])*\)\s*(?:Tj|\'|")|(?:\[(?:[^\[\]]|\\\\.)*\])\s*TJ|<[0-9A-Fa-f\s]+>\s*Tj/s',
            $content,
            $tokens
        );

        foreach ($tokens[0] as $token) {
            if (preg_match('/^\[(.*)\]\s*TJ$/s', $token, $m)) {
                preg_match_all('/\((?:\\\\.|[^()\\\\])*\)|<[0-9A-Fa-f\s]+>/', $m[1], $pieces);
                foreach ($pieces[0] as $piece) {
                    $out .= $this->decodePiece($piece, $cmap);
                }
                $out .= ' ';
            } elseif (preg_match('/^<([0-9A-Fa-f\s]+)>\s*Tj$/s', $token, $m)) {
                $out .= $this->decodeHexRun($m[1], $cmap) . ' ';
            } elseif (preg_match('/^\((.*)\)\s*(?:Tj|\'|")$/s', $token, $m)) {
                $out .= $this->decodeLiteralString($m[1]) . ' ';
            }
        }

        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════
    // Nettoyage du texte extrait
    // ═══════════════════════════════════════════════════════════════════

    private function cleanText(string $text): string
    {
        // 1. Supprimer les caractères de contrôle (hors tab/newline/CR)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? $text;

        // 2. Nettoyer les séquences UTF-8 invalides (évite les échecs
        //    silencieux dans json_encode lors de l'appel à l'API Cohere)
        if (function_exists('mb_scrub')) {
            $text = mb_scrub($text, 'UTF-8');
        } else {
            $text = @iconv('UTF-8', 'UTF-8//IGNORE', $text)
                 ?: preg_replace('/[\x80-\xFF]/', '', $text)
                 ?: $text;
        }

        // 3. Reconstruire les mots coupés par trait d'union en fin de ligne
        //    (phénomène fréquent dans les PDF issus de logiciels bureautiques)
        $text = preg_replace('/-\n(?=[a-záàâéèêëîïôùûüç])/ui', '', $text) ?? $text;

        // 4. Compresser les espaces multiples sur une même ligne
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;

        // 5. Réduire les sauts de ligne excessifs (max 2 consécutifs)
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        // 6. Supprimer les lignes qui ne contiennent que des espaces
        $text = preg_replace('/^\s+$/m', '', $text) ?? $text;

        // 7. Recréer les sauts de paragraphe sur les lignes courtes suivies
        //    d'une majuscule (heuristique : probable fin de paragraphe dans
        //    un PDF sans structure Markdown)
        $text = preg_replace('/([.!?])\n([A-ZÁÀÂÉÈÊËÎÏÔÙÛÜÇ])/u', "$1\n\n$2", $text) ?? $text;

        return trim($text);
    }

    /** Retourne true si le texte est vide ou ne contient que des espaces. */
    private function isEmpty(string $text): bool
    {
        return trim($text) === '';
    }
}
