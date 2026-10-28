<?php
use Enicore\Rivena\App;
use Enicore\Rivena\Classes\Config;
use Enicore\Rivena\Classes\Session;
use Enicore\Rivena\Classes\Web;

/**
 * Returns the asset URL with its version appended as ?v=, e.g. "/img/logo.png?v=3f2a...".
 */
if (!function_exists('w_url')) {
    function w_url(string $path): string {
        return Web::versionedUrl($path);
    }
}

/**
 * Returns a script tag for the asset URL with its version appended as ?v=.
 */
if (!function_exists('w_script')) {
    function w_script(string $path, array $attr = []): string {
        return Web::scriptTag($path, $attr);
    }
}

/**
 * Returns a stylesheet <link> tag for the asset URL with its version appended as ?v=.
 */
if (!function_exists('w_style')) {
    function w_style(string $path, array $attr = []): string {
        return Web::styleTag($path, $attr);
    }
}

/**
 * Returns an icon <link> tag for the asset URL with its version appended as ?v=.
 */
if (!function_exists('w_icon')) {
    function w_icon(string $path, array $attr = []): string {
        return Web::iconTag($path, $attr);
    }
}

/**
 * Returns an <img> tag for the asset URL with its version appended as ?v=.
 */
if (!function_exists('w_image')) {
    function w_image(string $path, array $attr = []): string {
        return Web::imageTag($path, $attr);
    }
}

/**
 * Returns the classes for <html> from the cookies rivena.js sets: "dark" or "light", and "sidebar-collapsed".
 */
if (!function_exists('w_html_class')) {
    function w_html_class(): string {
        return (($_COOKIE['theme'] ?? '') === 'light' ? 'light' : 'dark')
            . (isset($_COOKIE['sidebar-collapsed']) ? ' sidebar-collapsed' : '');
    }
}

/**
 * Returns the text for <title>: "<page title> - <site name>", or the site name alone (HTML-escaped).
 */
if (!function_exists('w_site_title')) {
    function w_site_title(string $title = ''): string {
        return htmlspecialchars(implode(' - ', array_filter([$title, Config::get('site_name')])));
    }
}

/**
 * Returns the session's CSRF token, or an empty string without a session.
 */
if (!function_exists('w_csrf')) {
    function w_csrf(): string {
        return session_status() === PHP_SESSION_ACTIVE ? Session::getCsrfToken() : '';
    }
}

/**
 * Returns the user area's path, user_path in the config, e.g. "/usr" (HTML-escaped).
 */
if (!function_exists('w_user_path')) {
    function w_user_path(): string {
        return htmlspecialchars(Config::get('user_path'));
    }
}

/**
 * Returns the path of the named route (HTML-escaped), or an empty string if the app removed it, e.g. for a menu link.
 */
if (!function_exists('w_route')) {
    function w_route(string $name, array $data = []): string {
        try {
            return htmlspecialchars(App::$routes->getRouteParser()->urlFor($name, $data));
        } catch (RuntimeException) {
            return '';
        }
    }
}

/**
 * Creates a script that passes page data to rivena_spa.js if the page was rendered by php instead of loaded by
 * rivena_spa. rivena_spa needs that data for further operation.
 */
if (!function_exists('w_spa_data')) {
    function w_spa_data(?array $page): string {
        return $page === null ? '' : '<script type="application/json" id="spa-page">' .
            json_encode($page, JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) .
            '</script>';
    }
}
