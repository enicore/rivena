<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use RuntimeException;
use Throwable;

/**
 * Renders the PHP views; a project's view (src/views) replaces Rivena's with the same name.
 * - a view gets its data as variables, and can include partials: $this->partial('admin/partials/pager')
 * - a layout gets the rendered view as $content, and the view's data
 * - error.php is the error pages' content ($code, $message)
 *
 * @package Enicore\Rivena
 */
class View
{
    /**
     * @var string[]
     */
    protected array $viewPaths;

    /**
     * Creates the renderer for the view directories; the first one that has a view wins (the project's views replace
     * Rivena's).
     *
     * @param array $viewPaths
     */
    public function __construct(array $viewPaths)
    {
        $this->viewPaths = array_map(fn($path) => rtrim($path, '/') . '/', $viewPaths);
    }

    /**
     * Returns the layout rendered with the view as $content; both get the data.
     *
     * @param string $view
     * @param array $data
     * @param string $layout
     * @return string
     * @throws RuntimeException
     */
    public function getLayoutHtml(string $view, array $data = [], string $layout = 'layout'): string
    {
        $data['content'] = $this->render($view, $data);

        return $this->render($layout, $data);
    }

    /**
     * Returns true if the view exists.
     *
     * @param string $view
     * @return bool
     */
    public function viewExists(string $view): bool
    {
        return $this->file($view) !== null;
    }

    /**
     * Returns the view rendered with the data as its variables, also a partial from a view:
     * $this->partial('admin/partials/pager').
     *
     * @param string $view
     * @param array $data
     * @return string
     * @throws RuntimeException
     */
    public function partial(string $view, array $data = []): string
    {
        return $this->render($view, $data);
    }

    /**
     * Returns the view's file from the first directory that has it, or null (also for a name with other characters than
     * a-z, 0-9, _, - and /, which could leave the view directories).
     *
     * @param string $view
     * @return string|null
     */
    private function file(string $view): ?string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#', $view)) {
            return null;
        }

        foreach ($this->viewPaths as $path) {
            if (is_file("$path$view.php")) {
                return "$path$view.php";
            }
        }

        return null;
    }

    /**
     * Returns the view's file rendered with the data. We're purposefully using long variable names ($viewName,
     * $viewData, etc.) so they don't block useful variable names like $view, $data in the view template.
     *
     * @param string $viewName
     * @param array $viewData
     * @return string
     */
    private function render(string $viewName, array $viewData): string
    {
        $viewFileName = $this->file($viewName) ?? throw new RuntimeException("View not found: $viewName");
        extract($viewData, EXTR_SKIP);
        ob_start();

        try {
            include $viewFileName;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string)ob_get_clean();
    }
}
