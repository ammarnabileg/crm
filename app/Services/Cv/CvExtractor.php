<?php

declare(strict_types=1);

namespace App\Services\Cv;

/**
 * Extracts plain text from an uploaded CV/résumé, in PURE PHP (the host has no
 * pdftotext/antiword), so it works on any server the installer passes.
 *
 * Supported well: DOCX (a zip of XML — fully parsed), RTF, plain text. Best-effort:
 * PDF (decodes FlateDecode content streams and pulls text-showing operators — good
 * for the vast majority of text-based CVs; a scanned/image-only PDF yields little
 * and is reported as low-confidence so the caller can ask for a different format).
 * Legacy .doc is binary — printable runs are salvaged as a last resort.
 *
 * The result is always sanitised (control chars stripped, whitespace collapsed) and
 * length-capped so it is safe to hand to the AI parser or store.
 */
final class CvExtractor
{
    /** Hard cap on returned text — a CV is a few pages; this bounds the AI prompt. */
    private const MAX_CHARS = 20000;

    /**
     * @return array{text:string, confident:bool} The extracted text and whether the
     *         extraction looks reliable (enough readable characters were recovered).
     */
    public function extract(string $path, string $originalName): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['text' => '', 'confident' => false];
        }

        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $raw = (string) @file_get_contents($path);

        $text = match ($ext) {
            'txt', 'md'   => $raw,
            'rtf'         => $this->fromRtf($raw),
            'docx'        => $this->fromDocx($path),
            'pdf'         => $this->fromPdf($raw),
            'doc'         => $this->fromLegacyDoc($raw),
            default       => $this->looksLikeText($raw) ? $raw : $this->fromPdf($raw),
        };

        $text = $this->sanitise($text);

        // "Confident" = we recovered a meaningful amount of readable text. A scanned
        // PDF or an unsupported binary tends to yield almost nothing.
        $confident = mb_strlen($text) >= 60 && $this->readableRatio($text) >= 0.6;

        return ['text' => $text, 'confident' => $confident];
    }

    /** DOCX: a zip whose word/document.xml holds the body; <w:p> are paragraphs. */
    private function fromDocx(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return '';
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === '') {
            return '';
        }

        // Paragraph + line breaks become newlines; tabs become spaces; drop the rest.
        $xml = preg_replace('/<w:(p|br|tab)\b[^>]*>/i', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** RTF: strip groups + control words, keep the visible runs. */
    private function fromRtf(string $raw): string
    {
        // \par / \line → newline; drop other control words and the {} group markers.
        $raw = preg_replace('/\\\\par[d]?(?![a-z])/i', "\n", $raw) ?? $raw;
        $raw = preg_replace('/\\\\[a-z]+-?\d* ?/i', ' ', $raw) ?? $raw;
        $raw = str_replace(['{', '}'], '', $raw);

        return $raw;
    }

    /**
     * PDF: decompress FlateDecode streams and pull text from (..)Tj and [..]TJ
     * operators. Handles the common text-PDF; not scanned images (no OCR available).
     */
    private function fromPdf(string $raw): string
    {
        if (! str_contains($raw, '%PDF')) {
            return '';
        }

        $out = [];

        // Each stream …endstream may be Flate-compressed; gather decoded payloads.
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $m)) {
            foreach ($m[1] as $chunk) {
                $decoded = @gzuncompress($chunk);
                if ($decoded === false) {
                    $decoded = @gzinflate($chunk);
                }
                $payload = $decoded !== false ? $decoded : $chunk;
                $out[] = $this->textFromPdfContent($payload);
            }
        }
        // Some PDFs keep text uncompressed outside streams — sweep the whole body too.
        $out[] = $this->textFromPdfContent($raw);

        return implode("\n", array_filter(array_map('trim', $out)));
    }

    /** Pull the string operands of Tj / TJ text operators out of a content stream. */
    private function textFromPdfContent(string $content): string
    {
        $pieces = [];

        // (literal string) Tj   and   [ (a) -250 (b) ] TJ
        if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)\s*Tj/', $content, $tj)) {
            foreach ($tj[0] as $hit) {
                $pieces[] = $this->decodePdfString($hit);
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arr)) {
            foreach ($arr[1] as $group) {
                if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $group, $strs)) {
                    foreach ($strs[0] as $s) {
                        $pieces[] = $this->decodePdfString($s);
                    }
                }
            }
        }

        $text = implode(' ', $pieces);
        // Newlines from text-positioning operators are lost; keep it as a flow.
        return $text;
    }

    /** Decode a PDF literal string: strip the parens, unescape \n \( \) \\ and octals. */
    private function decodePdfString(string $s): string
    {
        if (preg_match('/\((.*)\)/s', $s, $m) !== 1) {
            return '';
        }
        $body = $m[1];
        $body = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', static function (array $x): string {
            return match ($x[1]) {
                'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                '(' => '(', ')' => ')', '\\' => '\\',
                default => chr(octdec($x[1])),
            };
        }, $body) ?? $body;

        return $body;
    }

    /** Legacy .doc: salvage runs of printable characters (no real parser available). */
    private function fromLegacyDoc(string $raw): string
    {
        if (preg_match_all('/[\x20-\x7E\xA0-\xFF]{4,}/', $raw, $m)) {
            return implode("\n", $m[0]);
        }

        return '';
    }

    private function looksLikeText(string $raw): bool
    {
        return $raw !== '' && $this->readableRatio(mb_substr($raw, 0, 2000)) >= 0.85;
    }

    /** Fraction of printable/whitespace characters — a quick "is this real text?" test. */
    private function readableRatio(string $text): float
    {
        $len = strlen($text);
        if ($len === 0) {
            return 0.0;
        }
        $printable = strlen((string) preg_replace('/[^\P{C}\n\r\t]/u', '', $text));

        return $printable / $len;
    }

    private function sanitise(string $text): string
    {
        // Strip control chars except tab/newline, collapse runs of blank space.
        $text = (string) preg_replace('/[^\P{C}\n\t]/u', '', $text);
        $text = (string) preg_replace("/[ \t]+/", ' ', $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim($text);

        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        return $text;
    }
}
