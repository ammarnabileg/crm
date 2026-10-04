<?php

declare(strict_types=1);

namespace HaHireAI\Core\View;

use HaHireAI\Core\View\Exceptions\ViewNotFoundException;

/**
 * Minimal server-side view renderer. Templates are plain PHP files under
 * resources/views; output is escaped with the e() helper. Tailwind is the
 * styling layer; Alpine/Vanilla JS only when needed (docs/UI_GUIDELINES.md).
 */
final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $name, array $data = []): string
    {
        $file = $this->basePath . '/' . str_replace('.', '/', $name) . '.php';

        if (! is_file($file)) {
            throw new ViewNotFoundException("View [{$name}] not found at {$file}.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /**
     * Render a page view and wrap it in a layout (the layout receives $content).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $layoutData
     */
    public function page(string $name, array $data = [], string $layout = 'layouts.app', array $layoutData = []): string
    {
        $content = $this->render($name, $data);

        return $this->render($layout, array_merge($layoutData, ['content' => $content]));
    }
}
