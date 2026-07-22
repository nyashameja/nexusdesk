<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use RuntimeException;

/**
 * Plain-PHP view renderer with a simple layout mechanism.
 *
 * Views live in app/Views. A view may declare a layout by calling
 * $this->layout('layouts.app'); the rendered view content is then injected
 * into the layout as $content.
 */
final class View
{
    private ?string $layout = null;

    /** @var array<string, string> */
    private array $sections = [];

    public function __construct(private string $viewPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $content = $this->renderView($view, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $data['content'] = $content;
            return $this->renderView($layout, $data);
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderView(string $view, array $data): string
    {
        $file = $this->resolve($view);

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    private function resolve(string $view): string
    {
        $relative = str_replace('.', '/', $view) . '.php';
        return rtrim($this->viewPath, '/') . '/' . $relative;
    }

    /**
     * Called from within a view to select its layout.
     */
    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }
}
