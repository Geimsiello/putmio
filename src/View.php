<?php

declare(strict_types=1);

namespace PutMio;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(200);
        }

        $base = putmio_base_path() . '/templates';
        $file = $base . '/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Template non trovato: ' . putmio_e($template));
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $base . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    public static function renderInstall(string $template, array $data = []): void
    {
        self::render($template, $data, 'install/layout');
    }
}
