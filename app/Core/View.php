<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain-PHP template engine with layout inheritance and sections.
 *
 * Templates are PHP files under resources/views using dot notation
 * ("auth.login" -> resources/views/auth/login.php). A template may declare a
 * parent layout via $this->extends('layouts.app') and push content into named
 * sections via $this->section()/$this->endSection(). The engine intentionally
 * avoids a compile step so deployments need no build tooling.
 */
final class View
{
    /** @var array<string, string> */
    private array $sections = [];

    /** @var string[] */
    private array $sectionStack = [];

    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $viewPath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        // Each render is isolated; preserve outer state for nested renders.
        $previous = [$this->sections, $this->sectionStack, $this->layout];
        $this->sections = [];
        $this->sectionStack = [];
        $this->layout = null;

        $content = $this->renderFile($this->resolve($template), array_merge($this->shared, $data));

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            // A child template may either wrap its body in an explicit
            // section('content')/endSection(), or just echo it directly. Only
            // fall back to the direct output when no 'content' section was
            // declared, so an explicit section is never clobbered.
            if (! isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            $content = $this->renderFile($this->resolve($layout), array_merge($this->shared, $data));
        }

        [$this->sections, $this->sectionStack, $this->layout] = $previous;

        return $content;
    }

    private function renderFile(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            include $path;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';

        if (! is_file($file)) {
            throw new RuntimeException("View [{$template}] not found at {$file}.");
        }

        return $file;
    }

    // --- Directives available inside templates -----------------------------

    public function extends(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('endSection() called without a matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function include(string $template, array $data = []): void
    {
        echo $this->renderFile($this->resolve($template), array_merge($this->shared, $data));
    }
}
