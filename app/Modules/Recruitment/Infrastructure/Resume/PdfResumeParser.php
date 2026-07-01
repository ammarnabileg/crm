<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Infrastructure\Resume;

use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeParserInterface;
use Throwable;

/**
 * PDF résumé reader. Prefers the widely-used, pure-PHP `smalot/pdfparser`
 * library when it is installed (Composer) — the production path, high fidelity.
 * When the library is absent (e.g. a vendor-free boot), it falls back to a
 * NATIVE best-effort text extractor so the platform is never fragile: it still
 * recovers text from uncompressed / FlateDecode content streams, just with a
 * lower confidence the engine records honestly.
 *
 * The library is resolved via class_exists, so this file has NO hard dependency
 * on the package and the autoloader never fails if it is missing.
 */
final class PdfResumeParser implements ResumeParserInterface
{
    public function key(): string
    {
        return 'pdf';
    }

    public function supports(string $extension, string $mime): bool
    {
        return $extension === 'pdf' || $mime === 'application/pdf';
    }

    public function extract(string $path): ExtractedText
    {
        if (! is_file($path)) {
            return ExtractedText::empty($this->key());
        }

        // 1) Production path — smalot/pdfparser if available.
        $libraryClass = 'Smalot\\PdfParser\\Parser';
        if (class_exists($libraryClass)) {
            try {
                /** @var object $parser */
                $parser = new $libraryClass();
                /** @phpstan-ignore-next-line dynamic library call */
                $document = $parser->parseFile($path);
                $text = TextNormalizer::normalize((string) $document->getText());
                if ($text !== '') {
                    return new ExtractedText($text, 95, $this->key());
                }
            } catch (Throwable) {
                // Fall through to the native extractor.
            }
        }

        // 2) Native best-effort fallback.
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return ExtractedText::empty($this->key());
        }
        $text = TextNormalizer::normalize($this->nativeExtract($bytes));

        return $text === ''
            ? ExtractedText::empty($this->key())
            : new ExtractedText($text, 45, $this->key());
    }

    /**
     * Pull text from a PDF's content streams without any library. Handles the
     * common cases: FlateDecode-compressed and raw streams, with text drawn by
     * the Tj / TJ operators. Image-only / heavily-subset-encoded PDFs yield
     * little — hence the low confidence on this path.
     */
    private function nativeExtract(string $bytes): string
    {
        $chunks = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = $this->inflate($stream);
                $chunks[] = $this->textFromContent($decoded);
            }
        }

        // Some simple PDFs carry text operators outside compressed streams too.
        $chunks[] = $this->textFromContent($bytes);

        $text = trim(implode("\n", array_filter($chunks, static fn (string $c): bool => trim($c) !== '')));

        return $text;
    }

    private function inflate(string $stream): string
    {
        if (function_exists('gzuncompress')) {
            $out = @gzuncompress($stream);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }
        if (function_exists('zlib_decode')) {
            $out = @zlib_decode($stream);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }

        // Not compressed (or unknown filter) — use as-is.
        return $stream;
    }

    /** Extract the literal strings drawn by Tj / TJ operators from PDF content. */
    private function textFromContent(string $content): string
    {
        $out = [];

        // ( ... ) Tj   — single string show
        if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)\s*Tj/s', $content, $m)) {
            foreach ($m[1] as $s) {
                $out[] = $this->unescapePdfString($s);
            }
        }

        // [ (a) -250 (b) ] TJ   — array show with kerning
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $m)) {
            foreach ($m[1] as $arr) {
                if (preg_match_all('/\(((?:\\\\.|[^\\\\()])*)\)/s', $arr, $parts)) {
                    $line = '';
                    foreach ($parts[1] as $p) {
                        $line .= $this->unescapePdfString($p);
                    }
                    $out[] = $line;
                }
            }
        }

        return implode("\n", $out);
    }

    private function unescapePdfString(string $s): string
    {
        return strtr($s, [
            '\\n' => "\n", '\\r' => "\n", '\\t' => " ", '\\(' => '(',
            '\\)' => ')', '\\\\' => '\\',
        ]);
    }
}
