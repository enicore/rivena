<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use FilesystemIterator;
use MatthiasMullie\Minify\JS;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Builds the project's CSS and JS:
 * - src/styles/*.scss -> public/css/*.css (Dart Sass; files starting with _ are partials)
 * - Rivena's scripts, src/scripts/*.js and Alpine -> public/js/app.js (one minified file)
 *
 * Only changed sources are rebuilt. In dev, AssetsMiddleware runs it on each request; `vendor/bin/rivena build` runs
 * it by hand.
 *
 * @package Enicore\Rivena
 */
class Assets
{
    /**
     * Creates the builder for the project in the directory.
     *
     * @param string $root
     */
    public function __construct(private readonly string $root)
    {
    }

    /**
     * Builds the styles and scripts whose sources changed (all with force), and returns the built files' paths.
     *
     * @param bool $force
     * @return array
     * @throws RuntimeException
     */
    public function build(bool $force = false): array
    {
        // a page and its scripts and styles are requested at the same time: build once
        $lock = fopen(sys_get_temp_dir() . '/rivena_assets_' . md5($this->root) . '.lock', 'c');
        flock($lock, LOCK_EX);

        try {
            return array_merge($this->buildStyles($force), $this->buildScripts($force));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Compiles src/styles/*.scss into public/css with Dart Sass if they changed, and returns the built files' paths.
     *
     * @param bool $force
     * @return array
     * @throws RuntimeException
     */
    private function buildStyles(bool $force): array
    {
        $sources = "$this->root/src/styles";
        $files = glob("$sources/[!_]*.scss");

        if (!$files) {
            return [];
        }

        $targets = array_map(fn($file) => "$this->root/public/css/" . basename($file, '.scss') . '.css', $files);
        $rivena = dirname(__DIR__, 2) . '/assets/scss';

        if (!$force && $this->latest([$sources, $rivena]) < $this->oldest($targets)) {
            return [];
        }

        // directory mode: sass compiles all the files that aren't partials, in one run
        $process = @proc_open(
            ['sass', '--no-source-map', '--style=compressed', "$sources:$this->root/public/css"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (!$process) {
            throw new RuntimeException("Dart Sass (sass) isn't installed.");
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            // Sass wrote the error in the CSS, it's shown on the page
            throw new RuntimeException("Dart Sass failed: " . trim($output));
        }

        return array_map(fn($file) => substr($file, strlen($this->root) + 1), $targets);
    }

    /**
     * Bundles Rivena's scripts, the project's and Alpine into public/js/app.js if they changed, and returns its path.
     *
     * @param bool $force
     * @return array
     */
    private function buildScripts(bool $force): array
    {
        $sources = "$this->root/src/scripts";
        $target = "$this->root/public/js/app.js";
        $dist = dirname(__DIR__, 2) . '/assets/dist';
        $rivena = dirname(__DIR__, 2) . '/assets/js';

        if (!$force && $this->latest([$sources, $rivena, "$dist/alpine.min.js"]) < $this->oldest([$target])) {
            return [];
        }

        // the base classes first, so the controllers can extend them
        $minify = new JS("$rivena/rivena.js", "$rivena/rivena_spa.js");
        array_map(fn($file) => $minify->add($file), [
            ...$this->scripts("$rivena/admin", ['controller.js', 'confirm-dialog.js', 'table.js']),
            ...$this->scripts($sources, ['app.js']),
        ]);

        // Alpine is minified already; it starts when it loads, so it comes after the controllers
        $js = $minify->minify() . ";\n" . file_get_contents("$dist/alpine.min.js");

        is_dir(dirname($target)) || mkdir(dirname($target), 0775, true);
        file_put_contents("$target.tmp", $js);
        rename("$target.tmp", $target);

        return ['public/js/app.js'];
    }

    /**
     * Returns the directory's .js files: the first ones in the given order, then the others sorted.
     *
     * @param string $dir
     * @param array $first
     * @return array
     */
    private function scripts(string $dir, array $first): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $first = array_filter(array_map(fn($file) => "$dir/$file", $first), 'is_file');
        $files = array_filter($this->files($dir), fn($file) => str_ends_with($file, '.js') && !in_array($file, $first));
        sort($files);

        return [...$first, ...$files];
    }

    /**
     * Returns the latest modification time of the directories and the files in them.
     *
     * @param array $dirs
     * @return int
     */
    private function latest(array $dirs): int
    {
        $latest = 0;

        foreach ($dirs as $dir) {
            if (is_file($dir)) {
                $latest = max($latest, filemtime($dir));
                continue;
            }

            if (!is_dir($dir)) {
                continue;
            }

            $latest = max($latest, filemtime($dir));
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $latest = max($latest, $file->getMTime());
            }
        }

        return $latest;
    }

    /**
     * Returns the files' oldest modification time, or 0 if one of them doesn't exist.
     *
     * @param array $files
     * @return int
     */
    private function oldest(array $files): int
    {
        $oldest = PHP_INT_MAX;

        foreach ($files as $file) {
            $oldest = min($oldest, is_file($file) ? filemtime($file) : 0);
        }

        return $oldest;
    }

    /**
     * Returns the paths of all the files in the directory and its subdirectories.
     *
     * @param string $dir
     * @return array
     */
    private function files(string $dir): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS))
                 as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
