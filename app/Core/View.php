<?php

declare(strict_types=1);

namespace Orin\Core;

use RuntimeException;
use Throwable;

/**
 * Plain PHP template renderer with layout support.
 */
final class View
{
    public function __construct(private string $basePath)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->renderRaw($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderRaw($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    private function renderRaw(string $template, array $data): string
    {
        $file = $this->basePath . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('View [%s] not found at %s.', $template, $file));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
